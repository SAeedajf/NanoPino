<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Queue;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Registry\AbstractOwnedRegistry;

final class QueueRegistry extends AbstractOwnedRegistry
{
    protected function assertDefinition(OwnedDefinitionInterface $definition): void
    {
        if (!$definition instanceof QueueJobDefinition) {
            throw new \InvalidArgumentException('QueueRegistry accepts only QueueJobDefinition.');
        }
    }

    public function job(string $id): QueueJobDefinition
    {
        $definition=$this->get($id);
        if (!$definition instanceof QueueJobDefinition) {
            throw new \RuntimeException('Queue job type not registered: ' . $id);
        }
        return $definition;
    }
}
