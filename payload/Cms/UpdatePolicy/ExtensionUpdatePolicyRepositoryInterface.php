<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\UpdatePolicy;

interface ExtensionUpdatePolicyRepositoryInterface
{
    public function find(string $extensionId): ?ExtensionUpdatePolicy;
    public function save(ExtensionUpdatePolicy $policy): void;
    public function delete(string $extensionId): void;

    /** @return list<ExtensionUpdatePolicy> */
    public function all(): array;
}
