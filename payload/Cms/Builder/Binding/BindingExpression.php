<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

final readonly class BindingExpression
{
    /**
     * @param array<string,string|int|float|bool|null> $arguments
     */
    public function __construct(
        public string $source,
        public string $path,
        public array $arguments = [],
    ) {}
}
