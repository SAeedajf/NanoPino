<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Theme\Responsive;

use App\com_pinoox_cms\Cms\Theme\Design\ResolvedDesign;
use InvalidArgumentException;

final class BreakpointResolver
{
    private const UNITS = ['px', 'rem', 'em'];

    public function fromDesign(ResolvedDesign $design): BreakpointSet
    {
        $raw = $design->tokens['breakpoints'] ?? [];

        if ($raw === []) {
            $raw = [
                'sm' => '36rem',
                'md' => '48rem',
                'lg' => '64rem',
                'xl' => '80rem',
            ];
        }

        if (!is_array($raw) || array_is_list($raw)) {
            throw new InvalidArgumentException('Design breakpoints must be an object.');
        }

        if (count($raw) > 16) {
            throw new InvalidArgumentException('Too many design breakpoints.');
        }

        $items = [];
        foreach ($raw as $id => $value) {
            $id = strtolower(trim((string)$id));
            if (preg_match('/^[a-z][a-z0-9_-]{0,31}$/', $id) !== 1) {
                throw new InvalidArgumentException('Invalid breakpoint ID: ' . $id);
            }

            $value = strtolower(trim((string)$value));
            if (preg_match('/^([0-9]+(?:\.[0-9]+)?)(px|rem|em)$/', $value, $match) !== 1) {
                throw new InvalidArgumentException('Invalid breakpoint value: ' . $value);
            }

            $number = (float)$match[1];
            $unit = $match[2];

            if (!in_array($unit, self::UNITS, true) || $number <= 0 || $number > 10000) {
                throw new InvalidArgumentException('Breakpoint value is outside the allowed range.');
            }

            $sort = match ($unit) {
                'px' => $number,
                'rem', 'em' => $number * 16.0,
                default => $number,
            };

            $items[] = new BreakpointDefinition($id, $value, $sort, $unit);
        }

        usort($items, static fn (BreakpointDefinition $a, BreakpointDefinition $b): int =>
            [$a->sortValue, $a->id] <=> [$b->sortValue, $b->id]
        );

        return new BreakpointSet($items);
    }
}
