<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

final class InMemoryExtensionOperationLock implements ExtensionOperationLockInterface
{
    /** @var array<string,true> */
    private array $locks = [];

    public function synchronized(string $extensionId, callable $operation): mixed
    {
        if (isset($this->locks[$extensionId])) {
            throw new ExtensionOperationConflictException('Another extension operation is already running.');
        }

        $this->locks[$extensionId] = true;
        try {
            return $operation();
        } finally {
            unset($this->locks[$extensionId]);
        }
    }
}
