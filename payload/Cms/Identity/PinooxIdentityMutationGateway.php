<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use Pinoox\Model\RoleModel;
use Pinoox\Model\UserModel;
use Pinoox\Portal\Access;
use Pinoox\Portal\Auth;

final class PinooxIdentityMutationGateway implements IdentityMutationGatewayInterface
{
    public function createUser(array $data): int
    {
        $user = Auth::create($data);
        return (int)$user->user_id;
    }

    public function updateUser(int $userId, array $data): bool
    {
        $user = Auth::record($userId);
        if (!$user instanceof UserModel) {
            return false;
        }

        $profileUpdated = Auth::updateProfile($userId, [
            'fname' => array_key_exists('fname', $data) ? trim((string)$data['fname']) : (string)($user->fname ?? ''),
            'lname' => array_key_exists('lname', $data) ? trim((string)$data['lname']) : (string)($user->lname ?? ''),
            'email' => array_key_exists('email', $data) ? trim((string)$data['email']) : (string)($user->email ?? ''),
            'username' => array_key_exists('username', $data) ? trim((string)$data['username']) : (string)($user->username ?? ''),
        ]);

        if (array_key_exists('mobile', $data)) {
            UserModel::where('user_id', $userId)->update(['mobile' => trim((string)$data['mobile'])]);
        }

        return $profileUpdated;
    }

    public function setStatus(int $userId, string $status): bool
    {
        return Auth::setStatus($userId, $status);
    }

    public function deleteUser(int $userId): bool
    {
        return Auth::remove($userId);
    }

    public function revokeSessions(int $userId): int
    {
        return Auth::revokeSessions($userId);
    }

    public function assignRole(int $userId, string $roleKey): bool
    {
        return Access::assignRole($userId, $roleKey);
    }

    public function detachRole(int $userId, string $roleKey): bool
    {
        $user = UserModel::find($userId);
        $role = RoleModel::where('role_key', $roleKey)->first();

        if (!$user || !$role) {
            return false;
        }

        return $user->roles()->detach([$role->role_id]) > 0;
    }
}
