<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block\Discovery;

use App\com_pinoox_cms\Cms\Block\BlockManifestFactory;
use Throwable;

final class DirectoryBlockDiscoverySource
{
    public function __construct(
        private readonly BlockManifestFactory $factory = new BlockManifestFactory(),
        private readonly int $maxBlocks = 512,
    ) {}

    public function discover(string $extensionRoot, string $owner, string $directory = 'blocks'): BlockDiscoveryResult
    {
        $root = realpath($extensionRoot);
        if ($root === false || !is_dir($root) || is_link($extensionRoot)) {
            return new BlockDiscoveryResult([], ['Extension root is missing or unsafe.']);
        }

        $relative = trim(str_replace('\\', '/', $directory), '/');
        if (
            $relative === ''
            || str_contains($relative, "\0")
            || in_array('..', explode('/', $relative), true)
            || str_starts_with($relative, '/')
        ) {
            return new BlockDiscoveryResult([], ['Block directory is unsafe.']);
        }

        $blocksRoot = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($blocksRoot) || is_link($blocksRoot)) {
            return new BlockDiscoveryResult([]);
        }

        $blocks = [];
        $problems = [];
        $seen = 0;

        foreach (scandir($blocksRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;

            if (++$seen > $this->maxBlocks) {
                $problems[] = 'Block discovery exceeds configured package block limit.';
                break;
            }

            $blockDir = $blocksRoot . DIRECTORY_SEPARATOR . $entry;
            if (!is_dir($blockDir) || is_link($blockDir)) continue;

            $realBlockDir = realpath($blockDir);
            if ($realBlockDir === false || !$this->inside($root, $realBlockDir)) {
                $problems[] = 'Block directory escapes extension root: ' . $entry;
                continue;
            }

            $manifest = $realBlockDir . DIRECTORY_SEPARATOR . 'block.json';
            if (!is_file($manifest) || is_link($manifest)) continue;

            try {
                $definition = $this->factory->fromJsonFile($manifest, $owner);
                $blocks[] = new DiscoveredBlock($definition, $manifest);
            } catch (Throwable $error) {
                $problems[] = $entry . ': ' . $error->getMessage();
            }
        }

        usort($blocks, static fn (DiscoveredBlock $a, DiscoveredBlock $b): int =>
            $a->definition->name <=> $b->definition->name
        );

        return new BlockDiscoveryResult($blocks, $problems);
    }

    private function inside(string $root, string $path): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/');
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return $path === $root || str_starts_with($path . '/', $root . '/');
    }
}
