<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Model;

use App\com_pinoox_cms\Cms\Database\CmsDatabase;
use Pinoox\Component\Database\Model;

/**
 * Base model for CMS-owned tables.
 *
 * Pinoox Model normally resolves connection/table from the ambient app/model
 * context. CMS runtime APIs can be hosted through another system app, so this
 * layer deliberately pins both values to com_pinoox_cms — the same package
 * context used by PINX migrations.
 */
abstract class CmsModel extends Model
{
    public function getConnectionName()
    {
        return CmsDatabase::connectionName();
    }

    public function getTable(): string
    {
        $logical = isset($this->table)
            ? (string) $this->table
            : strtolower(str_replace('\\', '', class_basename($this)));

        return CmsDatabase::tableName($logical);
    }
}
