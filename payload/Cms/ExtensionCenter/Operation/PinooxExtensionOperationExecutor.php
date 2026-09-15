<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Kernel\CmsKernel;
use App\com_pinoox_cms\Cms\Recovery\FileRecoveryPointRepository;
use App\com_pinoox_cms\Cms\Recovery\FilesystemSnapshotProvider;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\FaultInjectorInterface;
use App\com_pinoox_cms\Cms\Recovery\FaultInjection\NullFaultInjector;
use App\com_pinoox_cms\Cms\Recovery\PinooxMigrationSnapshotProvider;
use App\com_pinoox_cms\Cms\Recovery\RecoveryManager;
use App\com_pinoox_cms\Cms\Recovery\SafeModeManager;
use App\com_pinoox_cms\Cms\Security\Package\PinooxPinxPackagePreflight;
use Pinoox\Portal\App\AppEngine;
use Pinoox\Portal\Pinx;
use Pinoox\Component\Package\Pinx\PinxIdentity;

final readonly class PinooxExtensionOperationExecutor implements ExtensionOperationExecutorInterface
{
    private FaultInjectorInterface $faults;

    public function __construct(
        private string $storageRoot,
        private CmsKernel $kernel,
        private ?PinooxPinxPackagePreflight $preflight = null,
        ?FaultInjectorInterface $faults = null,
    ) {
        $this->faults = $faults ?? new NullFaultInjector();
    }

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
        $this->faults->checkpoint('extension.install_or_update.before');
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
        $pinooxPackage = $native->isApp() ? $native->package() : null;
        $recovery = $this->recoveryFor($destination, $pinooxPackage);
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
        $progress('snapshot', 'ok', $pinooxPackage !== null
            ? 'Filesystem and migration recovery point created.'
            : 'Filesystem recovery point created.');

        // The original NanoShell converter generated an ephemeral signing key
        // for every import. Rotate only that legacy identity, after the
        // recovery point exists, so the native installer can perform its
        // normal strict trust-chain check. Any failure restores the snapshot.
        $this->rotateLegacyConverterIdentity($native, $destination);

        $installer = Pinx::installer()->onStep(
            static function (string $step, string $status, string $message) use ($progress): void {
                $progress('pinx.' . $step, $status, $message);
            }
        );

        try {
            $this->faults->checkpoint('extension.install_or_update.before_native');
            $result = $installer->install(
                $request->package->localPath,
                [
                    'force' => false,
                    'skip_verify' => false,
                    // Signed lifecycle is mandatory at the native mutation boundary;
                    // callers cannot downgrade this requirement through options.
                    'require_signature' => true, // request->options cannot downgrade signed lifecycle
                    'reset_overrides' => false,
                ],
            );

            if (!$result->success) {
                return $this->recoverFailedOperation(
                    $request,
                    $recovery,
                    $point->id,
                    $pinooxPackage,
                    $result->message,
                    $progress,
                );
            }

            $this->faults->checkpoint('extension.install_or_update.after_native');
            AppEngine::__rebuild();
            $this->faults->checkpoint('extension.install_or_update.after_rebuild');
            $progress('runtime', 'ok', 'Pinoox AppEngine rebuilt after PINX operation.');
            return new ExtensionExecutionResult(true, $point->id, false, null);
        } catch (\Throwable $error) {
            $progress('pinx.failure', 'error', 'PINX operation threw before completion.');
            return $this->recoverFailedOperation(
                $request,
                $recovery,
                $point->id,
                $pinooxPackage,
                $error->getMessage(),
                $progress,
            );
        }
    }

    private function rotateLegacyConverterIdentity(\Pinoox\Component\Package\Pinx\PinxManifest $manifest, string $destination): void
    {
        if (!$manifest->isTheme() || !in_array($manifest->developer(), ['nanoshell-local-converter', 'NanoShell Local Converter'], true)) return;

        $identityPath = rtrim($destination, '/\\') . '/' . PinxIdentity::FILE;
        if (!is_file($identityPath)) return;
        $identity = PinxIdentity::read($destination);
        if (!is_array($identity)) {
            throw new \RuntimeException('Existing theme trust identity is invalid; update was stopped safely.');
        }
        if ((string)($identity['key_id'] ?? '') === 'nanoshell-local-converter-v2') return;

        if (is_link($identityPath) || !is_file($identityPath) || !unlink($identityPath)) {
            throw new \RuntimeException('Legacy converter trust identity could not be rotated safely.');
        }
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
        $recovery = $this->recoveryFor($destination, $definition->package());
        $point = $recovery->create(
            $request->extensionId,
            'uninstall',
            ['destination' => $destination, 'package' => $definition->package()],
        );
        $progress('snapshot', 'ok', 'Pre-uninstall filesystem and migration recovery point created.');

        $uninstaller = Pinx::uninstaller()->onStep(
            static function (string $step, string $status, string $message) use ($progress): void {
                $progress('pinx.' . $step, $status, $message);
            }
        );

        try {
            $this->faults->checkpoint('extension.uninstall.before_native');
            $result = $uninstaller->uninstallApp($definition->package());
            if (!$result->success) {
                return $this->recoverFailedOperation(
                    $request,
                    $recovery,
                    $point->id,
                    $definition->package(),
                    $result->message,
                    $progress,
                );
            }

            $this->faults->checkpoint('extension.uninstall.after_native');
            AppEngine::__rebuild();
            $this->faults->checkpoint('extension.uninstall.after_rebuild');
            return new ExtensionExecutionResult(true, $point->id);
        } catch (\Throwable $error) {
            return $this->recoverFailedOperation(
                $request,
                $recovery,
                $point->id,
                $definition->package(),
                $error->getMessage(),
                $progress,
            );
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

        $migrationReceipt = $point->providerReceipts['migrations'] ?? null;
        $package = is_array($migrationReceipt) ? (string)($migrationReceipt['package'] ?? '') : '';
        $this->recoveryFor($source, $package !== '' ? $package : null)->restore($id);
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

    private function recoveryFor(string $source, ?string $package = null): RecoveryManager
    {
        $providers = [
            new FilesystemSnapshotProvider(
                $source,
                rtrim($this->storageRoot, '/\\') . '/recovery/snapshots',
            ),
        ];
        if ($package !== null && preg_match('/^com_[a-z0-9][a-z0-9_]{1,126}$/', $package) === 1) {
            $providers[] = new PinooxMigrationSnapshotProvider($package);
        }

        return new RecoveryManager($this->recoveryRepository(), $providers, $this->faults);
    }

    private function recoverFailedOperation(
        ExtensionOperationRequest $request,
        RecoveryManager $recovery,
        string $recoveryPointId,
        ?string $package,
        ?string $message,
        callable $progress,
    ): ExtensionExecutionResult {
        try {
            $recovery->restore($recoveryPointId);
            AppEngine::__rebuild();
            $progress('recovery', 'ok', 'Filesystem/migration recovery completed.');
            return new ExtensionExecutionResult(false, $recoveryPointId, false, $message);
        } catch (\Throwable $error) {
            $progress('recovery', 'error', 'Recovery was incomplete; extension is being quarantined.');
            $this->quarantineFailedPackage($request, $package, $recoveryPointId);
            return new ExtensionExecutionResult(false, $recoveryPointId, true, $message ?? $error->getMessage());
        }
    }

    private function quarantineFailedPackage(
        ExtensionOperationRequest $request,
        ?string $package,
        string $recoveryPointId,
    ): void {
        if ($package !== null && $package !== 'com_pinoox_cms' && AppEngine::exists($package)) {
            try {
                AppEngine::config($package)->set('enable', false)->save();
                AppEngine::__rebuild();
            } catch (\Throwable) {
                // Safe Mode remains the final fail-closed boundary if package disable fails.
            }
        }

        $this->safeMode()->enable(
            'Extension operation recovery was incomplete.',
            $request->extensionId,
            $recoveryPointId,
        );
    }

    private function recoveryRepository(): FileRecoveryPointRepository
    {
        return new FileRecoveryPointRepository(rtrim($this->storageRoot, '/\\') . '/recovery/points');
    }

    private function safeMode(): SafeModeManager
    {
        return new SafeModeManager(
            rtrim($this->storageRoot, '/\\') . '/recovery/safe-mode.json',
            $this->faults,
        );
    }
}
