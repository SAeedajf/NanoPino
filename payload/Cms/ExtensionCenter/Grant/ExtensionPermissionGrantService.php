<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Grant;

final readonly class ExtensionPermissionGrantService
{
    public function __construct(private ExtensionPermissionGrantRepositoryInterface $repository) {}

    /** @return list<string> */
    public function currentPermissions(string $extensionId): array
    {
        return $this->repository->find($extensionId)?->permissions ?? [];
    }

    public function current(string $extensionId): ?ExtensionPermissionGrant
    {
        return $this->repository->find($extensionId);
    }

    /** @param list<string> $requested @return list<string> */
    public function newlyRequested(string $extensionId, array $requested): array
    {
        $current = $this->repository->find($extensionId);
        $granted = array_fill_keys($current?->permissions ?? [], true);

        return array_values(array_filter(
            array_values(array_unique($requested)),
            static fn (string $permission): bool => !isset($granted[$permission]),
        ));
    }

    /**
     * @param list<string> $permissions
     */
    public function approve(
        string $extensionId,
        string $version,
        int $versionCode,
        string $packageSha256,
        array $permissions,
        ?int $actorId,
    ): ExtensionPermissionGrant {
        sort($permissions);
        $grant = new ExtensionPermissionGrant(
            $extensionId,
            $version,
            $versionCode,
            $packageSha256,
            array_values(array_unique($permissions)),
            $actorId,
            microtime(true),
        );
        $this->repository->save($grant);
        return $grant;
    }

    public function revoke(string $extensionId): void
    {
        $this->repository->delete($extensionId);
    }
}
