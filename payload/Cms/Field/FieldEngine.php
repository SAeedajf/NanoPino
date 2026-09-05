<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Field;

use App\com_pinoox_cms\Cms\Content\ContentTypeDefinition;

final class FieldEngine
{
    public function __construct(
        private readonly FieldRegistry $registry,
        private readonly FieldValueNormalizer $normalizer = new FieldValueNormalizer(),
    ) {}

    public function definition(string $key): ?FieldDefinition
    {
        return $this->registry->definition($key);
    }

    /**
     * @param array<string,mixed> $input
     */
    public function prepare(
        ContentTypeDefinition $contentType,
        array $input,
        bool $partial = false,
    ): PreparedFieldValues {
        $allowed = array_fill_keys($contentType->fields, true);

        foreach (array_keys($input) as $fieldKey) {
            if (!isset($allowed[$fieldKey])) {
                throw new FieldValidationException(sprintf(
                    'Field "%s" is not registered for content type "%s".',
                    $fieldKey,
                    $contentType->identifier(),
                ));
            }
        }

        $document = [];
        $meta = [];
        $relations = [];
        $taxonomies = [];

        foreach ($contentType->fields as $fieldKey) {
            $definition = $this->registry->definition($fieldKey);
            if ($definition === null) {
                throw new FieldValidationException('Missing registered field: ' . $fieldKey);
            }

            $hasValue = array_key_exists($fieldKey, $input);
            if (!$hasValue && $partial) {
                continue;
            }

            $value = $hasValue ? $input[$fieldKey] : $definition->default;
            if (!$hasValue && $definition->required && $value === null) {
                throw new FieldValidationException('Required field is missing: ' . $fieldKey);
            }

            if (!$hasValue && $value === null) {
                continue;
            }

            $normalized = $this->normalizer->normalize($definition, $value);

            match ($definition->storage) {
                FieldStorageStrategy::Document => $document[$fieldKey] = $normalized,
                FieldStorageStrategy::Meta => $meta[$fieldKey] = $normalized,
                FieldStorageStrategy::Relation => $relations[$fieldKey] = is_array($normalized)
                    ? array_values(array_map('intval', $normalized))
                    : [(int)$normalized],
                FieldStorageStrategy::Taxonomy => $taxonomies[$fieldKey] = is_array($normalized)
                    ? array_values(array_map('intval', $normalized))
                    : [(int)$normalized],
                FieldStorageStrategy::Virtual,
                FieldStorageStrategy::External => throw new FieldValidationException(
                    'Read-only field cannot be supplied: ' . $fieldKey
                ),
            };
        }

        return new PreparedFieldValues($document, $meta, $relations, $taxonomies);
    }
}
