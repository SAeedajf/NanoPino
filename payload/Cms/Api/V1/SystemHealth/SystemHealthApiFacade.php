<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Api\V1\SystemHealth;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationDeniedException;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;
use App\com_pinoox_cms\Cms\Health\HealthRunner;
use App\com_pinoox_cms\Cms\Logging\CmsLoggerInterface;
use App\com_pinoox_cms\Cms\Logging\StructuredLogRecord;
use App\com_pinoox_cms\Cms\Support\SupportBundleBuilder;

final readonly class SystemHealthApiFacade
{
    public function __construct(
        private AuthorizationManager $auth,
        private HealthRunner $health,
        private CmsLoggerInterface $logs,
        private SupportBundleBuilder $support,
    ) {}

    public function health(?int $actorId = null): SystemHealthApiResponse
    {
        return $this->guard('system.health.view', $actorId, function (): array {
            $results = $this->health->runAll();
            return [
                'generated_at' => time(),
                'overall' => $this->health->overall($results)->value,
                'checks' => array_map(static fn ($item): array => $item->toArray(), $results),
            ];
        });
    }

    public function logs(
        ?int $actorId = null,
        int $limit = 100,
        int $activeWindowSeconds = 900,
        ?string $level = null,
        ?string $channel = null,
        ?string $search = null,
    ): SystemHealthApiResponse {
        return $this->guard('system.logs.view', $actorId, function () use ($limit, $activeWindowSeconds, $level, $channel, $search): array {
            $limit = max(1, min(500, $limit));
            $activeWindowSeconds = max(300, min(86400, $activeWindowSeconds));
            $level = $level !== null ? strtolower(trim($level)) : null;
            $channel = $channel !== null ? strtolower(trim($channel)) : null;
            $search = $search !== null ? trim($search) : null;
            $now = microtime(true);
            $cutoff = $now - $activeWindowSeconds;

            $raw = $this->logs->tail(500);
            $rows = [];
            $summarySource = [];
            $channels = [];
            $levels = [];

            foreach ($raw as $record) {
                if (!$record instanceof StructuredLogRecord) continue;
                $row = $record->toArray();
                $rowLevel = strtolower((string)($row['level'] ?? ''));
                $rowChannel = strtolower((string)($row['channel'] ?? 'cms'));
                $message = (string)($row['message'] ?? '');
                $context = is_array($row['context'] ?? null) ? $row['context'] : [];
                $errorId = isset($context['error_id']) && is_string($context['error_id']) ? $context['error_id'] : null;
                $timestamp = (float)($row['timestamp'] ?? 0);
                $isRecentError = $rowLevel === 'error' && $timestamp >= $cutoff;

                $enriched = $row + [
                    'error_id' => $errorId,
                    'age_seconds' => max(0, (int)floor($now - $timestamp)),
                    'activity' => $isRecentError ? 'active' : ($rowLevel === 'error' ? 'historical' : 'normal'),
                ];
                $summarySource[] = $enriched;
                $channels[$rowChannel] = true;
                $levels[$rowLevel] = true;

                if ($level !== null && $level !== '' && $rowLevel !== $level) continue;
                if ($channel !== null && $channel !== '' && $rowChannel !== $channel) continue;
                if ($search !== null && $search !== '') {
                    $haystack = strtolower($message . ' ' . ($row['correlation_id'] ?? '') . ' ' . ($errorId ?? ''));
                    if (!str_contains($haystack, strtolower($search))) continue;
                }

                $rows[] = $enriched;
                if (count($rows) >= $limit) break;
            }

            $errors = array_values(array_filter($summarySource, static fn (array $row): bool => ($row['level'] ?? '') === 'error'));
            $warnings = array_values(array_filter($summarySource, static fn (array $row): bool => ($row['level'] ?? '') === 'warning'));
            $activeErrors = array_values(array_filter($errors, static fn (array $row): bool => ($row['activity'] ?? '') === 'active'));
            $historicalErrors = array_values(array_filter($errors, static fn (array $row): bool => ($row['activity'] ?? '') === 'historical'));
            $healthResults = $this->health->runAll();

            return [
                'generated_at' => time(),
                'logs' => $rows,
                'summary' => [
                    'available_records' => count($raw),
                    'returned_records' => count($rows),
                    'errors' => count($errors),
                    'warnings' => count($warnings),
                    'active_errors' => count($activeErrors),
                    'historical_errors' => count($historicalErrors),
                    'last_error_at' => $errors[0]['timestamp'] ?? null,
                    'active_window_seconds' => $activeWindowSeconds,
                    'channels' => array_values(array_keys($channels)),
                    'levels' => array_values(array_filter(array_keys($levels))),
                ],
                'health' => [
                    'overall' => $this->health->overall($healthResults)->value,
                    'checks' => array_map(static fn ($item): array => $item->toArray(), $healthResults),
                ],
                'privacy' => [
                    'redacted' => true,
                    'structured' => true,
                    'support_bundle_excludes_content' => true,
                ],
            ];
        });
    }

    public function support(?int $actorId = null): SystemHealthApiResponse
    {
        return $this->guard('system.support.export', $actorId, fn (): array => [
            'bundle' => json_decode($this->support->build()->json(), true, 512, JSON_THROW_ON_ERROR),
        ]);
    }

    private function guard(string $capability, ?int $actorId, callable $callback): SystemHealthApiResponse
    {
        try {
            $this->auth->authorize(new AuthorizationRequest($capability, $actorId));
            return new SystemHealthApiResponse(200, ['data' => $callback()]);
        } catch (AuthorizationDeniedException) {
            return new SystemHealthApiResponse(403, ['error' => [
                'code' => 'system.forbidden',
                'message' => 'System diagnostics are not permitted.',
                'details' => [],
            ]]);
        } catch (\Throwable) {
            return new SystemHealthApiResponse(500, ['error' => [
                'code' => 'system.internal_error',
                'message' => 'Internal diagnostics error.',
                'details' => [],
            ]]);
        }
    }
}
