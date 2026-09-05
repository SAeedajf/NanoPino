<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Transaction;

/**
 * Portable/test adapter. Production Pinoox wiring should use PinooxBuilderTransaction.
 */
final class DirectBuilderTransaction implements BuilderTransactionInterface
{
    public function run(callable $callback): mixed
    {
        return $callback();
    }
}
