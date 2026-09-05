<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Settings;

use App\com_pinoox_cms\Cms\Audit\AuditLogger;
use App\com_pinoox_cms\Cms\Audit\AuditOutcome;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;

final class SettingsService
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsRepositoryInterface $repository,
        private readonly AuthorizationManager $authorization,
        private readonly AuditLogger $audit,
    ) {}

    public function get(
        string $key,
        SettingScope $scope,
        ?int $actorId = null,
        bool $inheritGlobal = true,
    ): mixed {
        $definition = $this->definition($key);
        $this->assertScope($definition, $scope);
        $this->authorize($definition->readPermission, $actorId, $scope);

        $record = $this->repository->find($key, $scope);
        if ($record !== null) {
            return $definition->sensitive ? '[REDACTED]' : $record->value;
        }

        if (
            $inheritGlobal
            && $scope->type !== \App\com_pinoox_cms\Cms\Authorization\ScopeType::Global
            && in_array(
                \App\com_pinoox_cms\Cms\Authorization\ScopeType::Global,
                $definition->scopes,
                true
            )
        ) {
            $global = $this->repository->find($key, SettingScope::global());
            if ($global !== null) {
                return $definition->sensitive ? '[REDACTED]' : $global->value;
            }
        }

        return $definition->sensitive && $definition->default !== null
            ? '[REDACTED]'
            : $definition->default;
    }

    public function set(
        string $key,
        SettingScope $scope,
        mixed $value,
        ?int $actorId = null,
        ?int $expectedVersion = null,
        ?string $correlationId = null,
    ): SettingRecord {
        $definition = $this->definition($key);
        $this->assertScope($definition, $scope);
        $this->authorize($definition->writePermission, $actorId, $scope);

        $coerced = SettingCodec::coerce($definition->type, $value);
        $this->validate($definition, $coerced, $scope);

        try {
            $record = $this->repository->put(
                $key,
                $scope,
                $definition->type,
                $coerced,
                $actorId,
                $expectedVersion,
            );

            $this->audit->log(
                'settings.set',
                $definition->owner(),
                AuditOutcome::Success,
                $actorId,
                $scope->type,
                $scope->id,
                'setting',
                $key,
                $correlationId,
                [
                    'setting_key' => $key,
                    'value_type' => $definition->type->value,
                    'sensitive' => $definition->sensitive,
                    'version' => $record->version,
                ],
            );

            return $record;
        } catch (\Throwable $e) {
            $this->audit->log(
                'settings.set',
                $definition->owner(),
                AuditOutcome::Failed,
                $actorId,
                $scope->type,
                $scope->id,
                'setting',
                $key,
                $correlationId,
                [
                    'setting_key' => $key,
                    'error' => $e->getMessage(),
                ],
            );
            throw $e;
        }
    }

    public function reset(
        string $key,
        SettingScope $scope,
        ?int $actorId = null,
        ?int $expectedVersion = null,
        ?string $correlationId = null,
    ): bool {
        $definition = $this->definition($key);
        $this->assertScope($definition, $scope);
        $this->authorize($definition->writePermission, $actorId, $scope);

        $deleted = $this->repository->delete($key, $scope, $expectedVersion);

        $this->audit->log(
            'settings.reset',
            $definition->owner(),
            AuditOutcome::Success,
            $actorId,
            $scope->type,
            $scope->id,
            'setting',
            $key,
            $correlationId,
            ['deleted' => $deleted],
        );

        return $deleted;
    }

    private function definition(string $key): SettingDefinition
    {
        $definition = $this->registry->definition($key);
        if ($definition === null) {
            throw new SettingValidationException('Setting is not registered: ' . $key);
        }

        return $definition;
    }

    private function assertScope(SettingDefinition $definition, SettingScope $scope): void
    {
        if (!$definition->allowsScope($scope)) {
            throw new SettingValidationException(sprintf(
                'Setting "%s" does not support scope "%s".',
                $definition->identifier(),
                $scope->type->value,
            ));
        }
    }

    private function authorize(?string $permission, ?int $actorId, SettingScope $scope): void
    {
        if ($permission === null) {
            return;
        }

        $this->authorization->authorize(new AuthorizationRequest(
            $permission,
            $actorId,
            $scope->type,
            $scope->id,
            'setting',
        ));
    }

    private function validate(
        SettingDefinition $definition,
        mixed $value,
        SettingScope $scope,
    ): void {
        if ($definition->validator === null) {
            return;
        }

        $result = ($definition->validator)($value, $scope);

        if ($result === false) {
            throw new SettingValidationException(
                'Setting validation failed: ' . $definition->identifier()
            );
        }

        if (is_string($result) && $result !== '') {
            throw new SettingValidationException($result);
        }
    }
}
