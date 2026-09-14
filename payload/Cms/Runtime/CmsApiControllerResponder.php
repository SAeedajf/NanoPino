<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use Pinoox\Component\Http\JsonResponse;
use RuntimeException;
use Throwable;

/**
 * Controller boundary for Facade-backed API calls.
 *
 * Facades normally return a typed response object, but service construction,
 * adapter resolution, or a future extension can still fail before the facade
 * gets a chance to map the exception. Keep that failure inside the CMS API
 * contract and attach a privacy-safe correlation id through the reporter.
 */
trait CmsApiControllerResponder
{
    protected function facadeResponse(
        callable $resolver,
        string $errorCode,
        string $publicMessage,
        string $operation,
    ): JsonResponse {
        $startedNs = hrtime(true);

        try {
            $response = $resolver();
            if (!is_object($response) || !isset($response->status, $response->body)) {
                throw new RuntimeException('Facade returned an invalid API response.');
            }

            $headers = isset($response->headers) && is_array($response->headers)
                ? $response->headers
                : [];

            return CmsApiResponse::fromFacade(
                (int) $response->status,
                is_array($response->body) ? $response->body : [],
                $headers,
            );
        } catch (AuthorizationDeniedException) {
            // Authorization failures are expected control-flow outcomes. They
            // must never be reported as server faults or surface as HTTP 500.
            return CmsApiResponse::error(
                'FORBIDDEN',
                'This operation is not permitted.',
                403,
            );
        } catch (\InvalidArgumentException $error) {
            return CmsApiResponse::error(
                $errorCode . '_INVALID',
                $error->getMessage(),
                422,
            );
        } catch (Throwable $error) {
            return CmsRuntimeErrorReporter::response(
                $error,
                $errorCode,
                $publicMessage,
                500,
                ['operation' => $operation],
            );
        } finally {
            CmsRuntimeServices::recordApiTiming($operation, $startedNs);
        }
    }
}
