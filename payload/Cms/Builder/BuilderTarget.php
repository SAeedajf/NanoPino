<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder;

use InvalidArgumentException;

final readonly class BuilderTarget
{
    public function __construct(
        public int $siteId,
        public BuilderTargetType $type,
        public string $key,
        public string $locale = 'fa',
    ) {
        if ($siteId < 1) {
            throw new InvalidArgumentException('Builder target site ID must be positive.');
        }

        if (
            trim($key) === ''
            || strlen($key) > 190
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:@\/-]{0,189}$/', $key) !== 1
            || str_contains($key, '..')
        ) {
            throw new InvalidArgumentException('Invalid Builder target key.');
        }

        if (preg_match('/^[A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid Builder target locale.');
        }
    }

    public function identifier(): string
    {
        return $this->type->value . ':' . $this->key . ':' . strtolower($this->locale);
    }
}
