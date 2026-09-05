<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Performance\Query;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;
use Illuminate\Database\Events\QueryExecuted;

/**
 * Request-local query probe bound to NanoPino's package connection.
 *
 * It observes SQL with placeholders (not interpolated bindings), caps retained
 * observations, and fails open for application traffic while reporting an
 * unbound state to the Performance API if the listener cannot be installed.
 */
final class PinooxQueryProbe implements QueryProbeInterface
{
    private const MAX_OBSERVATIONS = 1000;

    /** @var list<QueryObservation> */
    private array $queries = [];
    private bool $bound = false;
    private ?string $bindingError = null;

    private static ?self $instance = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function bind(): bool
    {
        if ($this->bound) {
            return true;
        }

        try {
            CmsDatabase::connection()->listen(function (QueryExecuted $event): void {
                $this->observe((string) $event->sql, (float) $event->time);
            });
            $this->bound = true;
            $this->bindingError = null;
        } catch (\Throwable $e) {
            $this->bound = false;
            $message = trim($e->getMessage());
            $this->bindingError = $message === ''
                ? $e::class
                : (function_exists('mb_substr') ? mb_substr($message, 0, 300) : substr($message, 0, 300));
        }

        return $this->bound;
    }

    public function observe(string $sql, float $durationMs): void
    {
        if (count($this->queries) >= self::MAX_OBSERVATIONS) {
            array_shift($this->queries);
        }

        $this->queries[] = new QueryObservation($sql, max(0.0, $durationMs), microtime(true));
    }

    public function isBound(): bool
    {
        return $this->bound;
    }

    public function bindingError(): ?string
    {
        return $this->bindingError;
    }

    public function count(): int
    {
        return count($this->queries);
    }

    public function totalMs(): float
    {
        return array_sum(array_map(
            static fn (QueryObservation $query): float => $query->durationMs,
            $this->queries,
        ));
    }

    public function observations(): array
    {
        return $this->queries;
    }
}
