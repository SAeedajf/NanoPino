<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Sdk\Registry;

use App\com_pinoox_cms\Cms\Contracts\OwnedDefinitionInterface;
use App\com_pinoox_cms\Cms\Block\Render\BlockRendererInterface;

interface SdkRegistryGatewayInterface
{
    public function register(OwnedDefinitionInterface $definition, bool $replace = false): void;

    public function registerBlockRenderer(
        string $blockType,
        string $owner,
        BlockRendererInterface $renderer,
    ): void;

    public function validate(): void;

    public function removeOwner(string $owner): int;

    /** @return list<array<string,mixed>> */
    public function diagnostics(): array;
}
