<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Runtime;

use Pinoox\Portal\Auth;

final class RuntimeActor
{
    public static function id(): ?int
    {
        try {
            Auth::boot();
            $id = Auth::id();

            if (is_int($id)) {
                return $id > 0 ? $id : null;
            }

            if (is_string($id) && preg_match('/^[1-9]\d*$/', $id) === 1) {
                $value = (int) $id;
                return $value > 0 ? $value : null;
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
