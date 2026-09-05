<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Render;

use RuntimeException;

final class BlockRendererRegistry
{
    /** @var array<string,array{owner:string,renderer:BlockRendererInterface}> */
    private array $renderers = [];

    public function register(string $blockType, string $owner, BlockRendererInterface $renderer): void
    {
        if (!$renderer->supports($blockType)) {
            throw new RuntimeException('Renderer does not support declared block type.');
        }

        $existing = $this->renderers[$blockType] ?? null;
        if ($existing !== null && $existing['owner'] !== $owner) {
            throw new RuntimeException('Renderer collision for block: ' . $blockType);
        }

        $this->renderers[$blockType] = ['owner' => $owner, 'renderer' => $renderer];
    }

    public function renderer(string $blockType): ?BlockRendererInterface
    {
        return $this->renderers[$blockType]['renderer'] ?? null;
    }

    public function owner(string $blockType): ?string
    {
        return $this->renderers[$blockType]['owner'] ?? null;
    }

    public function removeOwner(string $owner): int
    {
        $removed = 0;
        foreach ($this->renderers as $type => $row) {
            if ($row['owner'] === $owner) {
                unset($this->renderers[$type]);
                ++$removed;
            }
        }
        return $removed;
    }

    /** @return array<string,string> */
    public function diagnostics(): array
    {
        $result = [];
        foreach ($this->renderers as $type => $row) {
            $result[$type] = $row['owner'];
        }
        ksort($result);
        return $result;
    }
}
