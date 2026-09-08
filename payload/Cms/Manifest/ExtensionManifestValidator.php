<?php

declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Manifest;

use App\com_pinoox_cms\Cms\Exception\ManifestValidationException;
use App\com_pinoox_cms\Cms\Extension\ExtensionType;

final class ExtensionManifestValidator
{
    private const CMS_SCHEMA = 1;
    private const PINX_FORMAT = 'pinx';
    private const PINX_TYPES = ['app', 'theme'];
    private const REQUIRE_KEYS = ['php', 'pinoox', 'pincore', 'cms', 'luma'];
    private const THIRD_PARTY_CMS_KEYS = [
        'schema', 'extension_type', 'publisher', 'requires', 'dependencies',
        'optional_dependencies', 'conflicts', 'provides', 'replaces',
        'permissions', 'services', 'capabilities', 'abilities', 'hooks',
        'admin', 'api', 'frontend', 'blocks', 'theme', 'metadata',
        // Deprecated 0.x flat CMS theme profile keys. Keep until the documented
        // theme-profile migration window closes.
        'paths', 'features', 'minimum_cms', 'maximum_cms', 'template_extensions',
    ];

    /** @param array<string,mixed> $pinx */
    public function validate(array $pinx): void
    {
        $violations = $this->violations($pinx);
        if ($violations !== []) {
            throw new ManifestValidationException($violations);
        }
    }

    /** @param array<string,mixed> $pinx @return list<string> */
    public function violations(array $pinx): array
    {
        $errors = [];

        if (($pinx['format'] ?? null) !== self::PINX_FORMAT) {
            $errors[] = 'format must be "pinx".';
        }

        if ((int) ($pinx['format_version'] ?? 0) !== 1) {
            $errors[] = 'format_version must be 1.';
        }

        $pinxType = (string) ($pinx['type'] ?? '');
        if (!in_array($pinxType, self::PINX_TYPES, true)) {
            $errors[] = 'PINX type must be "app" or "theme".';
        }

        $cms = $pinx['cms'] ?? null;
        if (!is_array($cms)) {
            $errors[] = 'cms profile is required.';
            return $errors;
        }

        if ((int) ($cms['schema'] ?? 0) !== self::CMS_SCHEMA) {
            $errors[] = 'cms.schema must be 1.';
        }

        $typeValue = (string) ($cms['extension_type'] ?? '');
        $type = ExtensionType::tryFrom($typeValue);
        if ($type === null) {
            $errors[] = 'cms.extension_type is invalid.';
        } elseif ($type !== ExtensionType::CoreModule) {
            foreach (array_keys($cms) as $key) {
                if (!is_string($key) || !in_array($key, self::THIRD_PARTY_CMS_KEYS, true)) {
                    $errors[] = 'cms contains unsupported property: ' . (string)$key . '. Put extension-specific data under cms.metadata.';
                }
            }
        }

        $publisher = trim((string) ($cms['publisher'] ?? ''));
        if ($publisher === '' || preg_match('/^[a-z0-9][a-z0-9._-]{1,126}$/', $publisher) !== 1) {
            $errors[] = 'cms.publisher must be a stable machine identifier.';
        }

        if ($pinxType === 'app') {
            $package = trim((string) ($pinx['package'] ?? ''));
            if (!$this->validPackage($package)) {
                $errors[] = 'PINX app package is invalid.';
            }
            if ($type === ExtensionType::Theme) {
                $errors[] = 'A PINX app cannot declare cms.extension_type=theme.';
            }
        }

        if ($pinxType === 'theme') {
            $targetApp = trim((string) ($pinx['target_app'] ?? ''));
            $themeName = trim((string) ($pinx['theme_name'] ?? $pinx['package'] ?? ''));
            if (!$this->validPackage($targetApp)) {
                $errors[] = 'Theme target_app is invalid.';
            }
            if (preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $themeName) !== 1) {
                $errors[] = 'Theme theme_name is invalid.';
            }
            if ($type !== null && $type !== ExtensionType::Theme) {
                $errors[] = 'A PINX theme must declare cms.extension_type=theme.';
            }
        }

        $version = trim((string) ($pinx['version_name'] ?? ''));
        if ($version === '') {
            $errors[] = 'version_name is required.';
        }
        if ((int) ($pinx['version_code'] ?? 0) < 1) {
            $errors[] = 'version_code must be >= 1.';
        }

        $requires = $cms['requires'] ?? [];
        if (!is_array($requires)) {
            $errors[] = 'cms.requires must be an object/map.';
        } else {
            foreach ($requires as $key => $constraint) {
                if (!is_string($key) || !in_array($key, self::REQUIRE_KEYS, true)) {
                    $errors[] = 'cms.requires contains unsupported platform key.';
                    continue;
                }
                if (!is_string($constraint) || trim($constraint) === '') {
                    $errors[] = 'cms.requires constraints must be non-empty strings.';
                }
            }
        }

        foreach (['dependencies', 'optional_dependencies'] as $field) {
            $this->validateDependencyMap($cms[$field] ?? [], 'cms.' . $field, $errors);
        }

        $conflicts = $cms['conflicts'] ?? [];
        if (!is_array($conflicts)) {
            $errors[] = 'cms.conflicts must be an object/map.';
        } else {
            foreach ($conflicts as $package => $constraint) {
                if (!is_string($package) || !$this->validTarget($package)) {
                    $errors[] = 'cms.conflicts contains an invalid target.';
                }
                if (!is_string($constraint) || trim($constraint) === '') {
                    $errors[] = 'cms.conflicts constraints must be non-empty strings.';
                }
            }
        }

        foreach (['provides', 'replaces', 'permissions', 'services', 'capabilities', 'abilities', 'hooks'] as $field) {
            if (isset($cms[$field]) && !is_array($cms[$field])) {
                $errors[] = 'cms.' . $field . ' must be a list.';
                continue;
            }
            foreach (($cms[$field] ?? []) as $item) {
                if (!is_string($item) || trim($item) === '') {
                    $errors[] = 'cms.' . $field . ' must contain non-empty strings only.';
                    break;
                }
            }
        }

        foreach (['admin', 'api', 'frontend', 'theme', 'metadata'] as $field) {
            if (isset($cms[$field]) && !is_array($cms[$field])) {
                $errors[] = 'cms.' . $field . ' must be an object/map.';
            }
        }

        if ($type !== null && $type->requiresBlocksProfile()) {
            $blocks = $cms['blocks'] ?? null;
            if (!is_array($blocks)) {
                $errors[] = 'cms.blocks is required for block and block-package extensions.';
            } else {
                $directory = trim((string)($blocks['directory'] ?? ''));
                if (
                    $directory === ''
                    || str_contains($directory, "\0")
                    || str_starts_with(str_replace('\\', '/', $directory), '/')
                    || preg_match('/^[A-Za-z]:/', $directory) === 1
                    || in_array('..', explode('/', str_replace('\\', '/', $directory)), true)
                ) {
                    $errors[] = 'cms.blocks.directory must be a safe relative path.';
                }

                if (isset($blocks['max_blocks'])) {
                    $max = (int)$blocks['max_blocks'];
                    if ($max < 1 || $max > 512) {
                        $errors[] = 'cms.blocks.max_blocks must be between 1 and 512.';
                    }
                }
            }
        } elseif (isset($cms['blocks']) && !is_array($cms['blocks'])) {
            $errors[] = 'cms.blocks must be an object/map.';
        }

        return array_values(array_unique($errors));
    }

    /** @param list<string> $errors */
    private function validateDependencyMap(mixed $value, string $field, array &$errors): void
    {
        if (!is_array($value)) {
            $errors[] = $field . ' must be an object/map.';
            return;
        }

        foreach ($value as $package => $constraint) {
            if (is_int($package) && is_string($constraint)) {
                if (!$this->validTarget($constraint)) {
                    $errors[] = $field . ' contains an invalid target.';
                }
                continue;
            }

            if (!is_string($package) || !$this->validTarget($package)) {
                $errors[] = $field . ' contains an invalid target.';
            }

            if (is_string($constraint)) {
                if (trim($constraint) === '') {
                    $errors[] = $field . ' contains an empty constraint.';
                }
                continue;
            }

            if (is_array($constraint)) {
                $rule = $constraint['constraint'] ?? '*';
                if (!is_string($rule) || trim($rule) === '') {
                    $errors[] = $field . ' contains an invalid constraint.';
                }
                continue;
            }

            $errors[] = $field . ' contains an invalid dependency rule.';
        }
    }

    private function validPackage(string $package): bool
    {
        return preg_match('/^com_[a-z0-9][a-z0-9_]*$/', $package) === 1;
    }

    private function validTarget(string $target): bool
    {
        return preg_match('#^[a-z0-9][a-z0-9._:/-]{1,190}$#', $target) === 1;
    }
}
