<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;

use App\com_pinoox_cms\Cms\Logging\CorrelationId;
use App\com_pinoox_cms\Cms\Logging\LogLevel;
use Illuminate\Database\QueryException;
use PDOException;
use Pinoox\Component\Http\JsonResponse;
use Throwable;

/**
 * Privacy-safe runtime exception reporting for Admin APIs.
 *
 * Raw exception messages/stacks never leave the server response. The response
 * exposes a short Error ID and coarse category; the redacted details are stored
 * in the CMS structured log and can be viewed through the permission-protected
 * System Logs endpoint/support bundle.
 */
final class CmsRuntimeErrorReporter
{
    /** @param array<string,mixed> $context */
    public static function response(
        Throwable $exception,
        string $code,
        string $publicMessage,
        int $status = 500,
        array $context = [],
    ): JsonResponse {
        $correlation = CorrelationId::generate();
        $errorId = 'cms-' . gmdate('YmdHis') . '-' . substr($correlation->value, 0, 10);
        $category = self::category($exception);

        $missing = null;
        try {
            $missing = CmsRuntimeSchemaReconciler::missingTables();
        } catch (Throwable) {
            // Diagnostics must never replace the original exception.
        }

        $storage = [];
        foreach (['contents','settings','media_assets','media_usages','media_variants','builder_documents','builder_revisions','global_blocks','theme_previews'] as $table) {
            try {
                $storage[$table] = CmsDatabase::diagnostic($table);
            } catch (Throwable) {
                $storage[$table] = ['logical' => $table, 'exists' => null];
            }
        }

        $logContext = array_merge($context, [
            'error_id' => $errorId,
            'category' => $category,
            'exception' => $exception::class,
            'exception_message' => self::truncate($exception->getMessage(), 4000),
            'source_file' => basename($exception->getFile()),
            'source_line' => $exception->getLine(),
            'schema_ready' => is_array($missing) ? $missing === [] : null,
            'missing_tables' => is_array($missing) ? $missing : [],
            'cms_storage' => $storage,
        ]);

        try {
            CmsRuntimeServices::logger()->log(
                LogLevel::Error,
                $code . ': ' . $publicMessage,
                $logContext,
                $correlation,
                'cms.api',
            );
        } catch (Throwable $loggingError) {
            // Last-resort server log, intentionally no request secrets/stack.
            error_log(sprintf(
                '[%s] %s (%s); cms_logger=%s',
                $errorId,
                $code,
                $exception::class,
                $loggingError::class,
            ));
        }

        $details = [
            'error_id' => $errorId,
            'category' => $category,
            'schema_ready' => is_array($missing) ? $missing === [] : null,
        ];
        if (is_array($missing) && $missing !== []) {
            $details['missing_tables'] = $missing;
        }
        if ($category === 'database') {
            $details['storage'] = array_map(static fn (array $row): array => [
                'logical' => $row['logical'] ?? null,
                'physical' => $row['physical'] ?? null,
                'connection' => $row['connection'] ?? null,
                'exists' => $row['exists'] ?? null,
            ], $storage);
        }

        $response = CmsApiResponse::error($code, $publicMessage, $status, $details);
        $response->headers->set('X-CMS-Error-ID', $errorId);
        $response->headers->set('X-CMS-Error-Category', $category);

        return $response;
    }

    private static function category(Throwable $exception): string
    {
        return match (true) {
            $exception instanceof QueryException,
            $exception instanceof PDOException => 'database',
            $exception instanceof \TypeError => 'type',
            $exception instanceof \JsonException => 'serialization',
            $exception instanceof \InvalidArgumentException => 'validation',
            default => 'runtime',
        };
    }

    private static function truncate(string $value, int $max): string
    {
        $value = str_replace(["\0", "\r", "\n"], ['', '', ' '], trim($value));
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max);
        }
        return substr($value, 0, $max);
    }
}
