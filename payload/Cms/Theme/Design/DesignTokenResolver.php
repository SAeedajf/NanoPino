<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use InvalidArgumentException;

final class DesignTokenResolver
{
    public function __construct(
        private readonly DesignDocumentLoader $loader = new DesignDocumentLoader(),
        private readonly StyleVariationLoader $variations = new StyleVariationLoader(),
        private readonly DeepTokenMerge $merge = new DeepTokenMerge(),
        private readonly DesignSchemaValidator $validator = new DesignSchemaValidator(),
    ) {}

    /**
     * @param list<string> $themePaths active theme first, then parents
     * @param array<string,mixed> $runtimeOverrides
     */
    public function resolve(
        array $themePaths,
        string $designFile = 'design.json',
        string $variationDirectory = 'styles/variations',
        ?string $variation = null,
        array $runtimeOverrides = [],
    ): ResolvedDesign {
        $tokens = [];
        $sources = [];

        // Base must flow from oldest parent to active child.
        foreach (array_reverse($themePaths) as $path) {
            $document = $this->loader->load($path, $designFile);
            if ($document === null) {
                continue;
            }
            $tokens = $this->merge->merge($tokens, $document->tokens);
            $sources[] = $path . '/' . $designFile;
        }

        if ($variation !== null) {
            if (preg_match('/^[a-z0-9][a-z0-9._-]{0,95}$/', $variation) !== 1) {
                throw new InvalidArgumentException('Invalid style variation ID.');
            }
            $available = $this->variations->discover($themePaths, $variationDirectory);
            $selected = $available[$variation] ?? null;
            if ($selected === null) {
                throw new InvalidArgumentException('Unknown style variation: ' . $variation);
            }
            $tokens = $this->merge->merge($tokens, $selected->tokens);
            $sources[] = $selected->sourceThemePath . '/' . $variationDirectory . '/' . $variation . '.json';
        }

        if ($runtimeOverrides !== []) {
            $validated = $this->validator->validate(['schema' => 1, 'tokens' => $runtimeOverrides]);
            $tokens = $this->merge->merge($tokens, $validated->tokens);
            $sources[] = 'runtime';
        }

        return new ResolvedDesign($tokens, $sources, $variation);
    }
}
