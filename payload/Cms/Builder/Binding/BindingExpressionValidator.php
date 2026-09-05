<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

use InvalidArgumentException;

final readonly class BindingExpressionValidator
{
    public function __construct(private DataSourceRegistry $sources) {}

    public function validate(BindingExpression $expression): void
    {
        if ($this->sources->definition($expression->source) === null) {
            throw new InvalidArgumentException('Unknown Builder data source.');
        }

        if (
            $expression->path === ''
            || strlen($expression->path) > 190
            || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,189}$/', $expression->path) !== 1
            || str_contains($expression->path, '..')
        ) {
            throw new InvalidArgumentException('Invalid Builder binding path.');
        }

        if (count($expression->arguments) > 32) {
            throw new InvalidArgumentException('Too many Builder binding arguments.');
        }

        foreach ($expression->arguments as $key => $value) {
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', (string)$key) !== 1) {
                throw new InvalidArgumentException('Invalid Builder binding argument key.');
            }
            if (!is_scalar($value) && $value !== null) {
                throw new InvalidArgumentException('Builder binding arguments must be scalar.');
            }
            if (is_string($value) && strlen($value) > 500) {
                throw new InvalidArgumentException('Builder binding argument is too long.');
            }
        }

        if ($expression->source === 'query') {
            foreach (['sql', 'raw', 'whereRaw', 'expression'] as $blocked) {
                if (array_key_exists($blocked, $expression->arguments)) {
                    throw new InvalidArgumentException('Raw query expressions are not allowed in Builder binding.');
                }
            }
        }
    }
}
