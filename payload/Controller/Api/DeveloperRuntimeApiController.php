<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Controller\Api;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Extension\ExtensionType;
use App\com_pinoox_cms\Cms\Runtime\CmsApiResponse;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeErrorReporter;
use App\com_pinoox_cms\Cms\Runtime\CmsRuntimeServices;
use App\com_pinoox_cms\Cms\Sdk\Package\ExtensionPackageBlueprint;
use App\com_pinoox_cms\Cms\Sdk\Package\ExtensionStarterExportService;
use Pinoox\Component\Http\JsonResponse;
use Pinoox\Component\Http\Request;
use Pinoox\Component\Kernel\Controller\ApiController;

final class DeveloperRuntimeApiController extends ApiController
{
    public function starter(Request $request): JsonResponse
    {
        try {
            CmsRuntimeServices::authorization()->authorize(new AuthorizationRequest(
                'system.developer.generate',
                CmsRuntimeServices::actorId(),
            ));

            $payload = $this->payload($request);
            $type = ExtensionType::from((string)($payload['type'] ?? ''));
            if ($type === ExtensionType::CoreModule) {
                return CmsApiResponse::error(
                    'DEVELOPER_STARTER_TYPE_FORBIDDEN',
                    'Core Module starters cannot be generated from Admin.',
                    422,
                );
            }

            $versionCode = (int)($payload['version_code'] ?? 100);
            if ($versionCode < 1 || $versionCode > 2147483647) {
                return CmsApiResponse::error(
                    'DEVELOPER_STARTER_INVALID',
                    'Starter version code is invalid.',
                    422,
                );
            }

            $spec = new ExtensionPackageBlueprint(
                package: trim((string)($payload['package'] ?? '')),
                name: trim((string)($payload['name'] ?? '')),
                type: $type,
                version: trim((string)($payload['version'] ?? '0.1.0')),
                versionCode: $versionCode,
                publisher: trim((string)($payload['publisher'] ?? 'example-developer')),
                description: trim((string)($payload['description'] ?? '')),
                targetApp: $type === ExtensionType::Theme
                    ? trim((string)($payload['target_app'] ?? 'com_pinoox_cms'))
                    : null,
                themeName: $type === ExtensionType::Theme
                    ? trim((string)($payload['theme_name'] ?? ''))
                    : null,
            );

            $data = (new ExtensionStarterExportService(
                CmsRuntimeServices::storageRoot(),
            ))->generate($spec);

            return CmsApiResponse::ok($data, 201);
        } catch (AuthorizationDeniedException) {
            return CmsApiResponse::error(
                'FORBIDDEN',
                'Developer starter generation is not permitted.',
                403,
            );
        } catch (\ValueError|\InvalidArgumentException $e) {
            return CmsApiResponse::error(
                'DEVELOPER_STARTER_INVALID',
                $this->safeReason($e),
                422,
            );
        } catch (\Throwable $e) {
            return CmsRuntimeErrorReporter::response(
                $e,
                'DEVELOPER_STARTER_FAILED',
                'Developer starter could not be generated.',
                500,
                ['operation' => 'developer.starter.generate'],
            );
        }
    }

    /** @return array<string,mixed> */
    private function payload(Request $request): array
    {
        try {
            $data = $request->toArray();
        } catch (\Throwable) {
            $data = [];
        }
        return is_array($data) ? $data : [];
    }

    private function safeReason(\Throwable $error): string
    {
        $message = trim($error->getMessage());
        if (
            $message === ''
            || str_contains($message, '/')
            || str_contains($message, '\\')
        ) {
            return 'Starter request is invalid.';
        }

        return function_exists('mb_substr')
            ? mb_substr($message, 0, 240)
            : substr($message, 0, 240);
    }
}
