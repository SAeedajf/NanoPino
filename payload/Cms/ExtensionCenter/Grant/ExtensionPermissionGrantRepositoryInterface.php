<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Grant;

interface ExtensionPermissionGrantRepositoryInterface
{
    public function find(string $extensionId): ?ExtensionPermissionGrant;
    public function save(ExtensionPermissionGrant $grant): void;
    public function delete(string $extensionId): void;

    /** @return list<ExtensionPermissionGrant> */
    public function all(): array;
}
