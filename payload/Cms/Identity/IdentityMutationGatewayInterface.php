<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

interface IdentityMutationGatewayInterface
{
    /** @param array<string,mixed> $data */
    public function createUser(array $data): int;
    public function updateUser(int $userId, array $data): bool;
    public function setStatus(int $userId, string $status): bool;
    public function deleteUser(int $userId): bool;
    public function revokeSessions(int $userId): int;
    public function assignRole(int $userId, string $roleKey): bool;
    public function detachRole(int $userId, string $roleKey): bool;
}
