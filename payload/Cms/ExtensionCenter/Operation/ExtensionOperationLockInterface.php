<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\ExtensionCenter\Operation;

interface ExtensionOperationLockInterface
{
    public function synchronized(string $extensionId, callable $operation): mixed;
}
