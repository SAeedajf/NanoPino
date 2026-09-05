<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

interface UserLookupInterface
{
    public function exists(int $userId): bool;
}
