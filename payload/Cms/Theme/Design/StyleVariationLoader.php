<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Design;

use App\com_pinoox_cms\Cms\Theme\ThemeFileReader;
use InvalidArgumentException;

final class StyleVariationLoader
{
    public function __construct(
        private readonly ThemeFileReader $files = new ThemeFileReader(),
        private readonly DesignSchemaValidator $validator = new DesignSchemaValidator(),
        private readonly DeepTokenMerge $merge = new DeepTokenMerge(),
    ) {}

    /**
     * Active child theme deep-merges over parent variation with the same ID.
     *
     * @param list<string> $themePaths active first, parents later
     * @return array<string,StyleVariation>
     */
    public function discover(array $themePaths, string $directory = 'styles/variations'): array
    {
        $variations = [];

        foreach (array_reverse($themePaths) as $themePath) {
            foreach ($this->files->jsonFiles($themePath, $directory) as $file) {
                $id = strtolower(pathinfo($file, PATHINFO_FILENAME));
                if (preg_match('/^[a-z0-9][a-z0-9._-]{0,95}$/', $id) !== 1) {
                    throw new InvalidArgumentException('Invalid style variation ID.');
                }

                $raw = json_decode((string)file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($raw)) {
                    throw new InvalidArgumentException('Style variation root must be an object.');
                }

                $document = $this->validator->validate([
                    'schema' => (int)($raw['schema'] ?? 1),
                    'tokens' => is_array($raw['tokens'] ?? null) ? $raw['tokens'] : [],
                ]);

                $previous = $variations[$id] ?? null;
                $tokens = $previous instanceof StyleVariation
                    ? $this->merge->merge($previous->tokens, $document->tokens)
                    : $document->tokens;

                $variations[$id] = new StyleVariation(
                    $id,
                    trim((string)($raw['title'] ?? $previous?->title ?? $id)),
                    $tokens,
                    $themePath,
                );
            }
        }

        ksort($variations);
        return $variations;
    }
}
