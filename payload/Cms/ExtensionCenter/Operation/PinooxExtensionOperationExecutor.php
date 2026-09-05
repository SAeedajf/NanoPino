<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Recovery\FileRecoveryPointRepository;
use App\com_pinoox_cms\Cms\Recovery\FilesystemSnapshotProvider;
use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use App\com_pinoox_cms\Cms\Security\Package\PinooxPinxPackagePreflight;
use Pinoox\Component\Migration\Migrator;
use Pinoox\Portal\App\AppEngine;
use Pinoox\Portal\Pinx;

final readonly class PinooxExtensionOperationExecutor implements ExtensionOperationExecutorInterface
{
    public function __construct(
        private string $storageRoot,
        private CmsKernel $kernel,
        private ?PinooxPinxPackagePreflight $preflight = null,
    ) {}

    public function execute(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        return match ($request->type) {
            ExtensionOperationType::Install,
            ExtensionOperationType::Update => $this->installOrUpdate($request, $progress),
            ExtensionOperationType::Activate => $this->setActive($request, true, $progress),
            ExtensionOperationType::Deactivate => $this->setActive($request, false, $progress),
            ExtensionOperationType::Uninstall => $this->uninstall($request, $progress),
            ExtensionOperationType::Rollback => $this->rollback($request, $progress),
            ExtensionOperationType::Repair => $this->repair($request, $progress),
        };
    }

    private function installOrUpdate(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        if ($request->package === null || $request->inspection === null) {
            throw new \LogicException('PINX install/update requires inspected staged package.');
        }

        $digest = hash_file('sha256', $request->package->localPath);
        if (!is_string($digest) || !hash_equals($request->inspection->packageSha256, $digest)) {
            throw new \RuntimeException('Extension package changed after security inspection.');
        }

        $preflight = ($this->preflight ?? new PinooxPinxPackagePreflight())->inspectPath(
            $request->package->localPath,
        );
        $progress(
            'security_preflight',
            'ok',
            sprintf(
                'PINX preflight passed: %d entries, %d static finding(s).',
                $preflight->filePlan->count(),
                count($preflight->findings),
            ),
        );

        $native = Pinx::manifest($request->package->localPath);
        $native->validate();

        $cms = $request->inspection->manifest;
        if ($cms->identifier() !== $request->extensionId) {
            throw new \RuntimeException('Inspected extension identity changed before execution.');
        }

        $destination = $this->destination($native);
        $recovery = $this->recoveryFor($destination);
        $point = $recovery->create(
            $request->extensionId,
            $request->type->value,
            [
                'destination' => $destination,
                'package' => $cms->package(),
                'version' => $cms->version(),
                'version_code' => $cms->versionCode(),
                'sha256' => $request->inspection->packageSha256,
            ],
        );
        $progress('snapshot', 'ok', 'Filesystem recovery point created.');

        $before = $native->isApp() ? $this->migrated($native->package()) : [];

        $installer = Pinx::installer()->onStep(
            static function (string $step, string $status, string $message) use ($progress): void {
                $progress('pinx.' . $step, $status, $message);
            }
        );

        $result = $installer->install(
            $request->package->localPath,
            [
                'force' => false,
                'skip_verify' => false,
                'require_signature' => (bool)($request->options['require_signature'] ?? false),
                'reset_overrides' => false,
            ],
        );

        if ($result->success) {
            AppEngine::__rebuild();
            $progress('runtime', 'ok', 'Pinoox AppEngine rebuilt after PINX operation.');
            return new ExtensionExecutionResult(true, $point->id, false, null);
        }

        $rollbackErrors = [];
        if ($native->isApp()) {
            try {
                $after = $this->migrated($native->package());
                $new = array_values(array_diff($after, $before));
                if ($new !== []) {
                    (new Migrator(
                        $native->package(),
                        'rollback',
                        ['force' => true, 'use_transactions' => true],
                    ))->rollback(count($new));
                    $progress('migration_rollback', 'ok', count($new) . ' migration(s) rolled back.');
                }
            } catch (\Throwable $error) {
                $rollbackErrors[] = 'migration: ' . $error->getMessage();
                $progress('migration_rollback', 'error', 'Migration rollback failed.');
            }
        }

        try {
            $recovery->restore($point->id);
            AppEngine::__rebuild();
            $progress('filesystem_rollback', 'ok', 'Filesystem recovery point restored.');
        } catch (\Throwable $error) {
            $rollbackErrors[] = 'filesystem: ' . $error->getMessage();
            $progress('filesystem_rollback', 'error', 'Filesystem recovery failed.');
        }

        if ($rollbackErrors !== []) {
            $this->safeMode()->enable(
                'PINX extension operation rollback was incomplete.',
                $request->extensionId,
                $point->id,
            );
            return new ExtensionExecutionResult(false, $point->id, true, $result->message);
        }

        return new ExtensionExecutionResult(false, $point->id, false, $result->message);
    }

    private function setActive(
        ExtensionOperationRequest $request,
        bool $active,
        callable $progress,
    ): ExtensionExecutionResult {
        $definition = $this->kernel->extensions->extension($request->extensionId);
        if ($definition === null) throw new \RuntimeException('Extension is not registered in CMS.');
        if ($definition->type() === ExtensionType::Theme) {
            throw new \RuntimeException('Theme activation is managed by Appearance.');
        }
        if ($definition->package() === 'com_pinoox_cms' && !$active) {
            throw new \RuntimeException('CMS core extension cannot be deactivated.');
        }
        if (!AppEngine::exists($definition->package())) {
            throw new \RuntimeException('Pinoox app is missing.');
        }

        $config = AppEngine::config($definition->package());
        $config->set('enable', $active)->save();
        AppEngine::__rebuild();

        $progress($active ? 'activate' : 'deactivate', 'ok', $active ? 'Pinoox app enabled.' : 'Pinoox app disabled.');
        return new ExtensionExecutionResult(true);
    }

    private function uninstall(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        $definition = $this->kernel->extensions->extension($request->extensionId);
        if ($definition === null) throw new \RuntimeException('Extension is not registered in CMS.');
        if ($definition->package() === 'com_pinoox_cms') throw new \RuntimeException('CMS core cannot uninstall itself.');
        if ($definition->type() === ExtensionType::Theme) {
            throw new \RuntimeException('Theme uninstall is managed by Appearance.');
        }
        if (!AppEngine::exists($definition->package())) throw new \RuntimeException('Pinoox app is already missing.');

        $destination = AppEngine::path($definition->package());
        $recovery = $this->recoveryFor($destination);
        $point = $recovery->create(
            $request->extensionId,
            'uninstall',
            ['destination' => $destination, 'package' => $definition->package()],
        );
        $progress('snapshot', 'ok', 'Pre-uninstall recovery point created.');

        $uninstaller = Pinx::uninstaller()->onStep(
            static function (string $step, string $status, string $message) use ($progress): void {
                $progress('pinx.' . $step, $status, $message);
            }
        );
        $result = $uninstaller->uninstallApp($definition->package());

        if ($result->success) {
            AppEngine::__rebuild();
            return new ExtensionExecutionResult(true, $point->id);
        }

        try {
            $recovery->restore($point->id);
            AppEngine::__rebuild();
            return new ExtensionExecutionResult(false, $point->id, false, $result->message);
        } catch (\Throwable) {
            $this->safeMode()->enable(
                'Extension uninstall failed and filesystem recovery was incomplete.',
                $request->extensionId,
                $point->id,
            );
            return new ExtensionExecutionResult(false, $point->id, true, $result->message);
        }
    }

    private function rollback(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        $id = trim((string)$request->recoveryPointId);
        if ($id === '') throw new \InvalidArgumentException('Recovery point id is required.');

        $repository = $this->recoveryRepository();
        $point = $repository->find($id);
        if ($point === null || $point->extensionId !== $request->extensionId) {
            throw new \RuntimeException('Recovery point not found for this extension.');
        }

        $receipt = $point->providerReceipts['filesystem'] ?? null;
        $source = is_array($receipt) ? (string)($receipt['source'] ?? '') : '';
        if ($source === '') throw new \RuntimeException('Recovery point does not contain filesystem source.');

        $this->recoveryFor($source)->restore($id);
        AppEngine::__rebuild();
        $progress('rollback', 'ok', 'Extension recovery point restored.');
        return new ExtensionExecutionResult(true, $id);
    }

    private function repair(
        ExtensionOperationRequest $request,
        callable $progress,
    ): ExtensionExecutionResult {
        $definition = $this->kernel->extensions->extension($request->extensionId);
        if ($definition === null || !AppEngine::exists($definition->package())) {
            throw new \RuntimeException('Extension Pinoox app is unavailable.');
        }

        AppEngine::__rebuild();
        $config = AppEngine::config($definition->package())->get();
        if (!is_array($config)) throw new \RuntimeException('Extension app configuration is unreadable.');
        $progress('repair', 'ok', 'Pinoox app registry/configuration rebuilt and reloaded.');
        return new ExtensionExecutionResult(true);
    }

    private function destination(\Pinoox\Component\Package\Pinx\PinxManifest $manifest): string
    {
        if ($manifest->isTheme()) {
            return AppEngine::path($manifest->targetApp(), 'theme/' . $manifest->themeName());
        }
        if (AppEngine::exists($manifest->package())) return AppEngine::path($manifest->package());
        return Pinx::engine()->packageLoader->path($manifest->package());
    }

    /** @return list<string> */
    private function migrated(string $package): array
    {
        if (!AppEngine::exists($package)) return [];
        try { $rows = (new Migrator($package, 'status'))->status(); } catch (\Throwable) { return []; }

        $items = [];
        foreach ($rows as $row) {
            if (is_array($row) && ($row['status'] ?? null) === 'migrated' && is_string($row['migration'] ?? null)) {
                $items[] = $row['migration'];
            }
        }
        sort($items);
        return $items;
    }

    private function recoveryFor(string $source): RecoveryManager
    {
        return new RecoveryManager(
            $this->recoveryRepository(),
            [new FilesystemSnapshotProvider($source, rtrim($this->storageRoot, '/\\') . '/recovery/snapshots')],
        );
    }

    private function recoveryRepository(): FileRecoveryPointRepository
    {
        return new FileRecoveryPointRepository(rtrim($this->storageRoot, '/\\') . '/recovery/points');
    }

    private function safeMode(): SafeModeManager
    {
        return new SafeModeManager(rtrim($this->storageRoot, '/\\') . '/recovery/safe-mode.json');
    }
}
