<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\ScopeType;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Settings\SettingConcurrencyException;
use App\com_pinoox_cms\Cms\Settings\SettingDefinition;
use App\com_pinoox_cms\Cms\Settings\SettingScope;
use App\com_pinoox_cms\Cms\Settings\SettingValidationException;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class SettingsApiController extends ApiController
{
    public function index(): JsonResponse
    {
        try {
            $actor = CmsRuntimeServices::actorId();
            $service = CmsRuntimeServices::settings();
            $repository = CmsRuntimeServices::settingsRepository();
            $items = [];

            foreach (CmsRuntimeServices::kernel()->settings->definitions() as $definition) {
                if (!$definition instanceof SettingDefinition) {
                    continue;
                }

                $scope = $this->preferred($definition, $actor);
                if ($scope === null) {
                    continue;
                }

                try {
                    $value = $service->get($definition->identifier(), $scope, $actor, true);
                    $record = $repository->find($definition->identifier(), $scope);
                    $items[] = $this->row($definition, $scope, $value, $record?->version);
                } catch (AuthorizationDeniedException) {
                    // Permission-aware registry: omit settings the actor cannot read.
                }
            }

            return CmsApiResponse::ok(['items' => $items, 'site_id' => 1]);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'SETTING_LIST_FAILED',
                'Settings could not be loaded.',
                500,
                ['operation' => 'settings.list'],
            );
        }
    }

    public function show(Request $request, string $key): JsonResponse
    {
        try {
            $scope = $this->scope(
                (string) $request->query->get('scope_type', 'site'),
                $request->query->get('scope_id', 1),
            );
            $actor = CmsRuntimeServices::actorId();
            $definition = CmsRuntimeServices::kernel()->settings->definition($key);

            if (!$definition instanceof SettingDefinition) {
                return CmsApiResponse::error('SETTING_NOT_FOUND', 'Setting is not registered.', 404);
            }

            $value = CmsRuntimeServices::settings()->get($key, $scope, $actor, true);
            $record = CmsRuntimeServices::settingsRepository()->find($key, $scope);

            return CmsApiResponse::ok($this->row($definition, $scope, $value, $record?->version));
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Setting access is not permitted.', 403);
        } catch (\InvalidArgumentException|SettingValidationException $e) {
            return CmsApiResponse::error('SETTING_READ_INVALID', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'SETTING_READ_FAILED',
                'Setting could not be loaded.',
                500,
                ['operation' => 'settings.read', 'setting_key' => $key],
            );
        }
    }

    public function update(Request $request, string $key): JsonResponse
    {
        try {
            $payload = $this->requestPayload($request);
            if (!array_key_exists('value', $payload)) {
                return CmsApiResponse::error('SETTING_VALUE_REQUIRED', 'value is required.', 422);
            }

            $scope = $this->scope(
                (string) ($payload['scope_type'] ?? 'site'),
                $payload['scope_id'] ?? 1,
            );
            $expected = isset($payload['expected_version']) && (int) $payload['expected_version'] > 0
                ? (int) $payload['expected_version']
                : null;

            $record = CmsRuntimeServices::settings()->set(
                $key,
                $scope,
                $payload['value'],
                CmsRuntimeServices::actorId(),
                $expected,
                $request->headers->get('X-Correlation-ID'),
            );

            return CmsApiResponse::ok([
                'key' => $record->key,
                'scope' => $record->scope->toArray(),
                'value' => $record->value,
                'version' => $record->version,
                'updated_by' => $record->updatedBy,
                'updated_at' => $record->updatedAt,
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Setting update is not permitted.', 403);
        } catch (SettingConcurrencyException) {
            return CmsApiResponse::error('SETTING_VERSION_CONFLICT', 'Setting changed in another request. Reload and try again.', 409);
        } catch (SettingValidationException|\InvalidArgumentException $e) {
            return CmsApiResponse::error('SETTING_VALIDATION_FAILED', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'SETTING_UPDATE_FAILED',
                'Setting could not be saved.',
                500,
                ['operation' => 'settings.update', 'setting_key' => $key],
            );
        }
    }

    public function reset(Request $request, string $key): JsonResponse
    {
        try {
            $payload = $this->requestPayload($request);
            $scope = $this->scope(
                (string) ($payload['scope_type'] ?? 'site'),
                $payload['scope_id'] ?? 1,
            );
            $expected = isset($payload['expected_version']) && (int) $payload['expected_version'] > 0
                ? (int) $payload['expected_version']
                : null;

            $deleted = CmsRuntimeServices::settings()->reset(
                $key,
                $scope,
                CmsRuntimeServices::actorId(),
                $expected,
                $request->headers->get('X-Correlation-ID'),
            );

            return CmsApiResponse::ok([
                'key' => $key,
                'scope' => $scope->toArray(),
                'deleted' => $deleted,
            ]);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error('FORBIDDEN', 'Setting reset is not permitted.', 403);
        } catch (SettingConcurrencyException) {
            return CmsApiResponse::error('SETTING_VERSION_CONFLICT', 'Setting changed in another request. Reload and try again.', 409);
        } catch (\InvalidArgumentException $e) {
            return CmsApiResponse::error('SETTING_RESET_INVALID', $e->getMessage(), 422);
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'SETTING_RESET_FAILED',
                'Setting could not be reset.',
                500,
                ['operation' => 'settings.reset', 'setting_key' => $key],
            );
        }
    }

    private function preferred(SettingDefinition $definition, ?int $actor): ?SettingScope
    {
        $prefer = isset($definition->ui['prefer_scope']) && is_string($definition->ui['prefer_scope'])
            ? $definition->ui['prefer_scope']
            : null;

        if ($prefer === 'user' && $actor !== null && in_array(ScopeType::User, $definition->scopes, true)) {
            return new SettingScope(ScopeType::User, $actor);
        }
        if (in_array(ScopeType::Site, $definition->scopes, true)) {
            return new SettingScope(ScopeType::Site, 1);
        }
        if (in_array(ScopeType::Global, $definition->scopes, true)) {
            return SettingScope::global();
        }
        if ($actor !== null && in_array(ScopeType::User, $definition->scopes, true)) {
            return new SettingScope(ScopeType::User, $actor);
        }
        return null;
    }

    private function scope(string $type, mixed $id): SettingScope
    {
        $scope = ScopeType::tryFrom($type)
            ?? throw new \InvalidArgumentException('Invalid setting scope.');

        if ($scope === ScopeType::Global) {
            return SettingScope::global();
        }

        if ($scope === ScopeType::Site) {
            if ((int) $id !== 1) {
                throw new \InvalidArgumentException('Current CMS runtime supports site_id=1 only.');
            }
            return new SettingScope($scope, 1);
        }

        if ($scope === ScopeType::User) {
            $actor = CmsRuntimeServices::actorId();
            if ($actor === null || (int) $id !== $actor) {
                throw new \InvalidArgumentException('User scope must target current user.');
            }
            return new SettingScope($scope, $actor);
        }

        return new SettingScope($scope, trim((string) $id));
    }

    /** @return array<string,mixed> */
    private function row(SettingDefinition $definition, SettingScope $scope, mixed $value, ?int $version): array
    {
        return [
            'key' => $definition->identifier(),
            'owner' => $definition->owner(),
            'type' => $definition->type->value,
            'label' => $definition->label,
            'group' => $definition->group,
            'scopes' => $definition->scopeNames(),
            'scope' => $scope->toArray(),
            'value' => $value,
            'version' => $version,
            'default' => $definition->sensitive ? '[REDACTED]' : $definition->default,
            'sensitive' => $definition->sensitive,
            'read_permission' => $definition->readPermission,
            'write_permission' => $definition->writePermission,
            'ui' => $definition->ui,
        ];
    }

    /** @return array<string,mixed> */
    private function requestPayload(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = [];
        }
        return is_array($data) ? $data : [];
    }
}
