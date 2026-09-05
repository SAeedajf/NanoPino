<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Transaction;

interface BuilderTransactionInterface
{
    public function run(callable $callback): mixed;
}
