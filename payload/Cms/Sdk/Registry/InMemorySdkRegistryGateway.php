<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Registry;

use App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface;
use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Exception\RegistryCollisionException;
use App\com_pinoox_cms\Cms\Exception\RegistryOwnershipException;

final class InMemorySdkRegistryGateway implements SdkRegistryGatewayInterface
{
    /** @var array<string,OwnedDefinitionInterface> */
    private array $definitions = [];

    /** @var array<string,array{owner:string,renderer:BlockRendererInterface}> */
    private array $renderers = [];

    public function register(OwnedDefinitionInterface $definition, bool $replace = false): void
    {
        $key = $definition::class . ':' . $definition->identifier();
        $current = $this->definitions[$key] ?? null;

        if ($current !== null) {
            if (!$replace) {
                throw new RegistryCollisionException('SDK definition already registered: ' . $key);
            }
            if ($current->owner() !== $definition->owner()) {
                throw new RegistryOwnershipException('SDK definition owner mismatch.');
            }
        }

        $this->definitions[$key] = $definition;
    }

    public function registerBlockRenderer(
        string $blockType,
        string $owner,
        BlockRendererInterface $renderer,
    ): void {
        $current = $this->renderers[$blockType] ?? null;
        if ($current !== null && $current['owner'] !== $owner) {
            throw new RegistryOwnershipException('Block renderer is owned by another Extension.');
        }
        $this->renderers[$blockType] = ['owner' => $owner, 'renderer' => $renderer];
    }

    public function validate(): void
    {
        // Portable collector: domain validators are exercised by concrete registry tests.
    }

    public function removeOwner(string $owner): int
    {
        $removed = 0;
        foreach (array_keys($this->definitions) as $key) {
            if ($this->definitions[$key]->owner() === $owner) {
                unset($this->definitions[$key]);
                ++$removed;
            }
        }
        foreach (array_keys($this->renderers) as $key) {
            if ($this->renderers[$key]['owner'] === $owner) {
                unset($this->renderers[$key]);
                ++$removed;
            }
        }
        return $removed;
    }

    /** @return list<OwnedDefinitionInterface> */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    public function has(string $class, string $identifier): bool
    {
        return isset($this->definitions[$class . ':' . $identifier]);
    }

    public function renderer(string $blockType): ?BlockRendererInterface
    {
        return $this->renderers[$blockType]['renderer'] ?? null;
    }

    public function diagnostics(): array
    {
        $rows = [];
        foreach ($this->definitions as $definition) {
            $rows[] = [
                'registry' => 'memory',
                'identifier' => $definition->identifier(),
                'owner' => $definition->owner(),
                'definition' => $definition::class,
            ];
        }
        foreach ($this->renderers as $blockType => $row) {
            $rows[] = [
                'registry' => 'block_renderers',
                'identifier' => $blockType,
                'owner' => $row['owner'],
                'definition' => $row['renderer']::class,
            ];
        }
        return $rows;
    }
}
