<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Pattern;

use App\com_pinoox_cms\Cms\Theme\ThemeFileReader;
use InvalidArgumentException;

final class ThemePatternLoader
{
    public function __construct(
        private readonly ThemeFileReader $files = new ThemeFileReader(),
        private readonly ?ThemePatternDocumentValidatorInterface $documentValidator = null,
    ) {}

    /**
     * Child pattern with same ID overrides parent.
     *
     * @param list<string> $themePaths active first
     * @return array<string,ThemePattern>
     */
    public function discover(array $themePaths, string $directory = 'patterns'): array
    {
        $patterns = [];

        foreach (array_reverse($themePaths) as $themePath) {
            foreach ($this->files->jsonFiles($themePath, $directory) as $file) {
                $raw = json_decode((string)file_get_contents($file), true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($raw)) {
                    throw new InvalidArgumentException('Theme pattern root must be an object.');
                }

                $id = strtolower(trim((string)($raw['id'] ?? pathinfo($file, PATHINFO_FILENAME))));
                if (preg_match('/^[a-z0-9][a-z0-9._\/-]{0,127}$/', $id) !== 1 || str_contains($id, '..')) {
                    throw new InvalidArgumentException('Invalid theme pattern ID.');
                }

                $document = $raw['document'] ?? null;
                if (!is_array($document)) {
                    throw new InvalidArgumentException('Theme pattern must contain a structured document.');
                }

                $encoded = json_encode($document, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (strlen($encoded) > 1_048_576) {
                    throw new InvalidArgumentException('Theme pattern document exceeds 1 MiB.');
                }

                $this->documentValidator?->validate($document);

                $categories = is_array($raw['categories'] ?? null)
                    ? array_values(array_filter(array_map('strval', $raw['categories'])))
                    : [];

                $patterns[$id] = new ThemePattern(
                    $id,
                    trim((string)($raw['title'] ?? $id)),
                    $categories,
                    $document,
                    $themePath,
                );
            }
        }

        ksort($patterns);
        return $patterns;
    }
}
