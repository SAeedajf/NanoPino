<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Dependency;

use InvalidArgumentException;

/**
 * Standalone fallback used by package tests and constrained environments.
 * Production prefers composer/semver. Supports exact/comparison, ^, ~,
 * wildcard, AND (space/comma) and OR (||) constraints.
 */
final class PortableSemverConstraintEvaluator implements VersionConstraintEvaluatorInterface
{
    public function satisfies(string $version, string $constraint): bool
    {
        $version = $this->normalizeVersion($version);
        $constraint = trim($constraint);
        if ($constraint === '' || $constraint === '*' || strtolower($constraint) === 'any') {
            return true;
        }

        foreach (preg_split('/\s*\|\|\s*/', $constraint) ?: [] as $orPart) {
            if ($this->satisfiesAndGroup($version, trim($orPart))) {
                return true;
            }
        }

        return false;
    }

    private function satisfiesAndGroup(string $version, string $group): bool
    {
        if ($group === '') {
            return true;
        }

        $tokens = preg_split('/(?:\s*,\s*|\s+)/', trim($group)) ?: [];
        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }
            if (!$this->satisfiesToken($version, $token)) {
                return false;
            }
        }
        return true;
    }

    private function satisfiesToken(string $version, string $token): bool
    {
        if ($token === '*' || strtolower($token) === 'any') {
            return true;
        }

        if ($token[0] === '^') {
            $min = $this->normalizeVersion(substr($token, 1));
            [$major, $minor, $patch] = $this->parts($min);
            $max = $major > 0
                ? ($major + 1) . '.0.0'
                : ($minor > 0 ? '0.' . ($minor + 1) . '.0' : '0.0.' . ($patch + 1));
            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if ($token[0] === '~') {
            $raw = substr($token, 1);
            $segments = explode('.', preg_replace('/[^0-9.].*$/', '', $raw) ?: $raw);
            $min = $this->normalizeVersion($raw);
            [$major, $minor] = $this->parts($min);
            $max = count($segments) >= 3 ? $major . '.' . ($minor + 1) . '.0' : ($major + 1) . '.0.0';
            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (str_contains($token, '*') || str_contains(strtolower($token), 'x')) {
            $clean = str_replace(['X', 'x'], '*', $token);
            $parts = explode('.', $clean);
            $wild = array_search('*', $parts, true);
            if ($wild === false) {
                return false;
            }
            $prefix = array_slice($parts, 0, $wild);
            if ($prefix === []) {
                return true;
            }
            $minParts = array_pad(array_map('intval', $prefix), 3, 0);
            $min = implode('.', $minParts);
            if ($wild === 1) {
                $max = ((int) $prefix[0] + 1) . '.0.0';
            } else {
                $max = (int) $prefix[0] . '.' . ((int) ($prefix[1] ?? 0) + 1) . '.0';
            }
            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)?\s*(.+)$/', $token, $m) !== 1) {
            throw new InvalidArgumentException('Invalid version constraint token: ' . $token);
        }
        $op = $m[1] !== '' ? $m[1] : '=';
        $target = $this->normalizeVersion($m[2]);
        return version_compare($version, $target, $op === '=' ? '==' : $op);
    }

    private function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');
        if ($version === '') {
            throw new InvalidArgumentException('Version cannot be empty.');
        }
        $core = preg_split('/[-+]/', $version, 2)[0] ?? $version;
        $segments = explode('.', $core);
        while (count($segments) < 3) {
            $segments[] = '0';
        }
        $normalizedCore = implode('.', array_slice($segments, 0, 3));
        $suffix = substr($version, strlen($core));
        return $normalizedCore . $suffix;
    }

    /** @return array{0:int,1:int,2:int} */
    private function parts(string $version): array
    {
        $core = preg_split('/[-+]/', $version, 2)[0] ?? $version;
        $p = array_pad(array_map('intval', explode('.', $core)), 3, 0);
        return [$p[0], $p[1], $p[2]];
    }
}
