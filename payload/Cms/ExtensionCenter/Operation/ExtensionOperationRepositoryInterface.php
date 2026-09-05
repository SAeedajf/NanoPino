<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

interface ExtensionOperationRepositoryInterface
{
    public function save(ExtensionOperationRecord $operation): void;
    public function find(string $id): ?ExtensionOperationRecord;

    /** @return list<ExtensionOperationRecord> */
    public function forExtension(string $extensionId, int $limit = 50): array;

    public function activeForExtension(string $extensionId): ?ExtensionOperationRecord;
}
