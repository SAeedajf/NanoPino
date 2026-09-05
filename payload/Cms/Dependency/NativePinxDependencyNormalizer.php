<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

use InvalidArgumentException;

final class NativePinxDependencyNormalizer
{
    /** @return list<NativePinxDependencyRule> */
    public function normalize(mixed $depends): array
    {
        if (!is_array($depends) || $depends === []) {
            return [];
        }
        $rules = [];
        if (array_is_list($depends)) {
            foreach ($depends as $package) {
                if (is_string($package) && trim($package) !== '') {
                    $rules[] = new NativePinxDependencyRule(trim($package));
                }
            }
            return $rules;
        }

        foreach ($depends as $package => $constraint) {
            if (is_int($package) && is_string($constraint)) {
                $rules[] = new NativePinxDependencyRule(trim($constraint));
                continue;
            }
            if (!is_string($package) || trim($package) === '') {
                continue;
            }
            $optional = false;
            $minCode = null;
            if (is_array($constraint)) {
                $optional = (bool) ($constraint['optional'] ?? false);
                if (isset($constraint['min_code'])) {
                    $minCode = max(0, (int) $constraint['min_code']);
                } elseif (isset($constraint['version_code'])) {
                    $minCode = max(0, (int) $constraint['version_code']);
                }
            } elseif (is_int($constraint)) {
                $minCode = max(0, $constraint);
            } elseif (is_string($constraint)) {
                $c = trim($constraint);
                if ($c !== '' && $c !== '*' && strtolower($c) !== 'any') {
                    if (preg_match('/^>=?\s*(\d+)$/', $c, $m) !== 1 && !ctype_digit($c)) {
                        throw new InvalidArgumentException('Unsupported native PINX version-code constraint: ' . $c);
                    }
                    $minCode = (int) ($m[1] ?? $c);
                }
            }
            $rules[] = new NativePinxDependencyRule(trim($package), $optional, $minCode);
        }
        return $rules;
    }
}
