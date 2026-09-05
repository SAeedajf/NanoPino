<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use Pinoox\Model\UserModel;

final class PinooxUserLookup implements UserLookupInterface
{
    public function exists(int $userId): bool
    {
        return $userId > 0 && UserModel::query()->whereKey($userId)->exists();
    }
}
