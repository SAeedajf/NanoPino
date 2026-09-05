<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Support;

use InvalidArgumentException;

final readonly class DefinitionIdentifier
{
    public function __construct(public string $value)
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('Invalid definition identifier: ' . $value);
        }
    }

    public static function isValid(string $value): bool
    {
        return preg_match('#^[a-z0-9][a-z0-9._:/-]{1,190}$#', $value) === 1;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
