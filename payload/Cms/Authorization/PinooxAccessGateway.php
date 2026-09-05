<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Authorization;

use Pinoox\Model\UserModel;
use Pinoox\Portal\Access;
use Pinoox\Portal\Auth;

/**
 * Pinoox-native authorization bridge.
 *
 * API PermissionFlow already authenticates the current request and calls
 * Access::can($permission) against the active Auth user. Re-resolving that same
 * user by numeric id can enter a different App/Transport scope. For the current
 * actor, preserve the exact native PermissionFlow semantics and only use an
 * explicit subject for genuinely different users.
 */
final class PinooxAccessGateway implements AccessGatewayInterface
{
    public function can(string $capability, ?int $subjectId = null): bool
    {
        Auth::boot();

        $currentId = self::positiveId(Auth::id());
        if ($subjectId === null || ($currentId !== null && $subjectId === $currentId)) {
            return Access::can($capability);
        }

        return Access::can($capability, $subjectId);
    }

    public function abilities(?int $subjectId = null): array
    {
        Auth::boot();

        $current = Auth::user();
        $currentId = $current instanceof UserModel ? self::positiveId($current->user_id) : null;

        if ($subjectId === null || ($currentId !== null && $subjectId === $currentId)) {
            $user = $current;
        } else {
            $user = UserModel::find($subjectId);
        }

        if (!$user instanceof UserModel) {
            return [];
        }

        return array_values(Access::abilitiesFor($user));
    }

    private static function positiveId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^[1-9]\d*$/', $value) === 1) {
            $id = (int) $value;
            return $id > 0 ? $id : null;
        }

        return null;
    }
}
