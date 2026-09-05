<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Template;

use InvalidArgumentException;

final readonly class TemplateRequest
{
    /** @param array<string,string|int|null> $variables */
    public function __construct(
        public string $kind,
        public array $variables = [],
    ) {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $kind) !== 1) {
            throw new InvalidArgumentException('Invalid template request kind.');
        }

        foreach ($variables as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)$key) !== 1) {
                throw new InvalidArgumentException('Invalid template variable key.');
            }
            if ($value !== null && !is_scalar($value)) {
                throw new InvalidArgumentException('Template variables must be scalar.');
            }
        }
    }
}
