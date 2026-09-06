<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Security\Access;

use Pinoox\Component\Access\AccessConfig;
use Pinoox\Model\UserModel;
use Pinoox\Support\Platform;

final class PlatformSuperTransitionReadiness
{
    /** @return array{platform_super:bool,total_platform_accounts:int,explicit_super_accounts:int,implicit_only_accounts:int,ready:bool,error:?string} */
    public function inspect(): array
    {
        try {
            $config = AccessConfig::resolve();
            $superRoles = array_values(array_filter(
                $config['super_roles'] ?? [],
                static fn (mixed $role): bool => is_string($role) && $role !== '',
            ));

            $users = UserModel::query()
                ->where('app', Platform::PACKAGE)
                ->with('roles')
                ->get();

            $explicit = 0;
            $implicitOnly = 0;

            foreach ($users as $user) {
                $groupKey = trim((string)($user->group_key ?? ''));
                $roleKeys = $user->roles
                    ? $user->roles->pluck('role_key')->map(static fn ($role): string => (string)$role)->all()
                    : [];

                $hasExplicitSuper =
                    ($groupKey !== '' && in_array($groupKey, $superRoles, true))
                    || array_intersect($roleKeys, $superRoles) !== [];

                if ($hasExplicitSuper) {
                    $explicit++;
                } else {
                    $implicitOnly++;
                }
            }

            $total = (int)$users->count();
            $platformSuper = (bool)($config['platform_super'] ?? true);

            return [
                'platform_super' => $platformSuper,
                'total_platform_accounts' => $total,
                'explicit_super_accounts' => $explicit,
                'implicit_only_accounts' => $implicitOnly,
                'ready' => $platformSuper && $total > 0 && $implicitOnly === 0 && $explicit > 0,
                'error' => null,
            ];
        } catch (\Throwable $error) {
            return [
                'platform_super' => true,
                'total_platform_accounts' => 0,
                'explicit_super_accounts' => 0,
                'implicit_only_accounts' => 0,
                'ready' => false,
                'error' => $error->getMessage(),
            ];
        }
    }
}
