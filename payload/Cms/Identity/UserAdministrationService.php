<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Identity;

use App\com_pinoox_cms\Cms\Authorization\AuthorizationManager;
use App\com_pinoox_cms\Cms\Authorization\AuthorizationRequest;

final class UserAdministrationService
{
    public function __construct(
        private readonly AuthorizationManager $authorization,
        private readonly IdentityMutationGatewayInterface $identity,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(?int $actorId, array $data): int
    {
        $this->authorization->authorize(new AuthorizationRequest('users.create', $actorId));
        return $this->identity->createUser($data);
    }

    /** @param array<string,mixed> $data */
    public function update(?int $actorId, int $userId, array $data): bool
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.update',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        return $this->identity->updateUser($userId, $data);
    }

    public function setStatus(?int $actorId, int $userId, string $status): bool
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.update',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        if ($actorId !== null && $actorId === $userId && $status !== 'active') {
            throw new \InvalidArgumentException('Current administrator cannot disable their own account.');
        }
        return $this->identity->setStatus($userId, $status);
    }

    public function assignRole(?int $actorId, int $userId, string $roleKey): bool
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.roles.manage',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        return $this->identity->assignRole($userId, $roleKey);
    }

    public function detachRole(?int $actorId, int $userId, string $roleKey): bool
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.roles.manage',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        if ($actorId !== null && $actorId === $userId) {
            throw new \InvalidArgumentException('Current administrator cannot remove their own role from this control plane.');
        }
        return $this->identity->detachRole($userId, $roleKey);
    }

    public function revokeSessions(?int $actorId, int $userId): int
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.sessions.revoke',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        return $this->identity->revokeSessions($userId);
    }

    public function delete(?int $actorId, int $userId): bool
    {
        $this->authorization->authorize(new AuthorizationRequest(
            'users.delete',
            $actorId,
            resourceType: 'user',
            resourceId: $userId,
            resourceOwnerId: $userId,
        ));
        if ($actorId !== null && $actorId === $userId) {
            throw new \InvalidArgumentException('Current administrator cannot delete their own account.');
        }
        return $this->identity->deleteUser($userId);
    }
}
