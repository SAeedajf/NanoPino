<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Block;

final class BlockManifestFactory
{
    /** @param array<string,mixed> $raw */
    public function fromArray(array $raw, string $owner): BlockDefinition
    {
        $errors = [];

        if ((int)($raw['schema'] ?? 0) !== 1) {
            $errors[] = 'block.json schema must be 1.';
        }

        $name = strtolower(trim((string)($raw['name'] ?? '')));
        if (preg_match('#^[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._-]{1,63}$#', $name) !== 1) {
            $errors[] = 'name must be namespace/name.';
        }

        $title = trim((string)($raw['title'] ?? ''));
        if ($title === '' || strlen($title) > 190) {
            $errors[] = 'title is required and must be <= 190 bytes.';
        }

        $category = strtolower(trim((string)($raw['category'] ?? 'common')));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $category) !== 1) {
            $errors[] = 'category is invalid.';
        }

        $schemaVersion = (int)($raw['version'] ?? 0);
        if ($schemaVersion < 1) {
            $errors[] = 'version must be an integer >= 1.';
        }

        $packageVersion = trim((string)($raw['package_version'] ?? '1.0.0'));
        if (
            $packageVersion === ''
            || strlen($packageVersion) > 64
            || preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $packageVersion) !== 1
        ) {
            $errors[] = 'package_version must be semantic versioning compatible.';
        }

        $attributes = [];
        $rawAttributes = $raw['attributes'] ?? [];
        if (!is_array($rawAttributes)) {
            $errors[] = 'attributes must be an object.';
        } else {
            foreach ($rawAttributes as $attributeName => $config) {
                if (!is_string($attributeName) || preg_match('/^[a-z][a-zA-Z0-9_]{0,63}$/', $attributeName) !== 1) {
                    $errors[] = 'attribute name is invalid.';
                    continue;
                }
                if (!is_array($config)) {
                    $errors[] = 'attribute config must be an object.';
                    continue;
                }

                $type = BlockAttributeType::tryFrom((string)($config['type'] ?? ''));
                if ($type === null) {
                    $errors[] = 'attribute type is invalid: ' . $attributeName;
                    continue;
                }

                $rules = is_array($config['rules'] ?? null) ? $config['rules'] : [];

                if (isset($rules['pattern'])) {
                    $pattern = (string)$rules['pattern'];
                    if (strlen($pattern) > 256 || @preg_match($pattern, '') === false) {
                        $errors[] = 'attribute pattern rule is invalid: ' . $attributeName;
                    }
                }

                $attribute = new BlockAttributeDefinition(
                    $attributeName,
                    $type,
                    (bool)($config['required'] ?? false),
                    $config['default'] ?? null,
                    $rules,
                );

                if (
                    array_key_exists('default', $config)
                    && $config['default'] !== null
                    && !(new \App\com_pinoox_cms\Cms\Block\Document\BlockAttributeValidator())->valid(
                        $attribute,
                        $config['default'],
                    )
                ) {
                    $errors[] = 'attribute default is invalid: ' . $attributeName;
                }

                $attributes[$attributeName] = $attribute;
            }
        }

        $supports = is_array($raw['supports'] ?? null) ? $raw['supports'] : [];
        $allowedSupportKeys = [
            'anchor', 'align', 'color', 'typography', 'spacing', 'dimensions',
            'responsive', 'visibility', 'className', 'html', 'reusable',
        ];
        foreach (array_keys($supports) as $key) {
            if (!in_array((string)$key, $allowedSupportKeys, true)) {
                $errors[] = 'unsupported block support: ' . $key;
            }
        }

        $children = $raw['children'] ?? false;
        $allowsChildren = false;
        $allowedChildren = [];
        if (is_bool($children)) {
            $allowsChildren = $children;
            $allowedChildren = $children ? ['*'] : [];
        } elseif (is_array($children)) {
            $allowsChildren = true;
            $allowedChildren = array_values(array_unique(array_map('strval', $children)));
        } else {
            $errors[] = 'children must be boolean or list of block names.';
        }

        $permissions = is_array($raw['permissions'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $raw['permissions']))))
            : [];
        foreach ($permissions as $permission) {
            if (preg_match('#^[a-z0-9][a-z0-9._:/-]{1,190}$#', $permission) !== 1) {
                $errors[] = 'block permission identifier is invalid.';
            }
        }

        $slots = [];
        $rawSlots = $raw['slots'] ?? [];
        if (!is_array($rawSlots)) {
            $errors[] = 'slots must be an object.';
        } else {
            foreach ($rawSlots as $slotName => $slotConfig) {
                if (!is_string($slotName) || preg_match('/^[a-z][a-z0-9_-]{0,63}$/', $slotName) !== 1) {
                    $errors[] = 'slot name is invalid.';
                    continue;
                }
                if (!is_array($slotConfig)) {
                    $errors[] = 'slot config must be an object.';
                    continue;
                }
                $allowed = $slotConfig['allowed'] ?? ['*'];
                if (!is_array($allowed) || !array_is_list($allowed)) {
                    $errors[] = 'slot allowed must be a list.';
                    continue;
                }
                foreach ($allowed as $allowedType) {
                    $allowedType = (string)$allowedType;
                    if (
                        $allowedType !== '*'
                        && preg_match('#^[a-z0-9][a-z0-9._-]{1,63}/[a-z0-9][a-z0-9._-]{1,63}$#', $allowedType) !== 1
                    ) {
                        $errors[] = 'slot contains invalid allowed block type.';
                    }
                }
                $slots[$slotName] = [
                    'allowed' => array_values(array_unique(array_map('strval', $allowed))),
                    'multiple' => (bool)($slotConfig['multiple'] ?? true),
                    'required' => (bool)($slotConfig['required'] ?? false),
                ];
            }
        }

        if ($slots !== [] && !$allowsChildren) {
            $errors[] = 'slots require children support.';
        }

        $migrations = [];
        $rawMigrations = $raw['migrations'] ?? [];
        if (!is_array($rawMigrations) || !array_is_list($rawMigrations)) {
            $errors[] = 'migrations must be a list.';
        } else {
            foreach ($rawMigrations as $migration) {
                if (!is_array($migration)) {
                    $errors[] = 'migration declaration must be an object.';
                    continue;
                }
                $from = (int)($migration['from'] ?? 0);
                $to = (int)($migration['to'] ?? 0);
                $class = trim((string)($migration['class'] ?? ''));
                if (
                    $from < 1
                    || $to <= $from
                    || $class === ''
                    || strlen($class) > 255
                    || preg_match('~^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,254}$~', $class) !== 1
                ) {
                    $errors[] = 'migration declaration is invalid.';
                    continue;
                }
                $migrations[] = ['from' => $from, 'to' => $to, 'class' => $class];
            }
        }

        $editor = is_array($raw['editor'] ?? null) ? $raw['editor'] : [];
        $renderer = is_array($raw['renderer'] ?? null) ? $raw['renderer'] : [];

        if (!isset($editor['component']) || trim((string)$editor['component']) === '') {
            $errors[] = 'editor.component is required.';
        } elseif (isset($editor['component'])) {
            $component = (string)$editor['component'];
            if (
                strlen($component) > 255
                || str_contains($component, "\0")
                || str_starts_with($component, '/')
                || preg_match('/^[A-Za-z]:/', $component) === 1
                || in_array('..', explode('/', str_replace('\\', '/', $component)), true)
                || preg_match('~^[A-Za-z0-9_./-]+\.(?:vue|js|ts)$~', $component) !== 1
            ) {
                $errors[] = 'editor component entrypoint is invalid.';
            }
        }

        if (!isset($renderer['class']) || trim((string)$renderer['class']) === '') {
            $errors[] = 'renderer.class is required.';
        } elseif (isset($renderer['class'])) {
            $class = (string)$renderer['class'];
            if (
                strlen($class) > 255
                || preg_match('~^[A-Za-z_\\\\][A-Za-z0-9_\\\\]{0,254}$~', $class) !== 1
            ) {
                $errors[] = 'renderer class entrypoint is invalid.';
            }
        }

        $icon = trim((string)($raw['icon'] ?? 'box'));
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $icon) !== 1) {
            $errors[] = 'icon is invalid.';
        }

        if ($errors !== []) {
            throw new BlockManifestValidationException(array_values(array_unique($errors)));
        }

        return new BlockDefinition(
            id: 'block:' . $name,
            ownerId: $owner,
            name: $name,
            title: $title,
            category: $category,
            icon: $icon,
            schemaVersion: $schemaVersion,
            version: $packageVersion,
            attributes: $attributes,
            supports: $supports,
            allowsChildren: $allowsChildren,
            allowedChildren: $allowedChildren,
            permissions: $permissions,
            slots: $slots,
            migrations: $migrations,
            editor: $editor,
            renderer: $renderer,
        );
    }

    public function fromJsonFile(string $file, string $owner): BlockDefinition
    {
        if (!is_file($file) || is_link($file)) {
            throw new BlockManifestValidationException(['block.json is missing or unsafe.']);
        }
        $size = filesize($file);
        if (!is_int($size) || $size < 1 || $size > 262144) {
            throw new BlockManifestValidationException(['block.json exceeds 256 KiB.']);
        }

        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            throw new BlockManifestValidationException(['block.json could not be read.']);
        }

        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new BlockManifestValidationException([
                'block.json is invalid JSON: ' . $error->getMessage(),
            ]);
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new BlockManifestValidationException(['block.json root must be an object.']);
        }

        return $this->fromArray($decoded, $owner);
    }
}
