<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Transaction;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;

final class PinooxBuilderTransaction implements BuilderTransactionInterface
{
    public function run(callable $callback): mixed
    {
        return CmsDatabase::transaction($callback);
    }
}
