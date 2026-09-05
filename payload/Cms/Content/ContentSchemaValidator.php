<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Content;

use App\com_pinoox_cms\Cms\Field\FieldRegistry;
use App\com_pinoox_cms\Cms\Field\FieldStorageStrategy;
use App\com_pinoox_cms\Cms\Field\FieldType;
use App\com_pinoox_cms\Cms\Taxonomy\TaxonomyRegistry;
use LogicException;

final class ContentSchemaValidator
{
    public function validate(
        ContentTypeRegistry $contentTypes,
        FieldRegistry $fields,
        TaxonomyRegistry $taxonomies,
    ): void {
        foreach ($contentTypes->definitions() as $contentType) {
            foreach ($contentType->fields as $fieldKey) {
                $field = $fields->definition($fieldKey);
                if ($field === null) {
                    throw new LogicException(sprintf(
                        'Content type "%s" references unknown field "%s".',
                        $contentType->identifier(),
                        $fieldKey,
                    ));
                }

                if ($field->type === FieldType::Taxonomy) {
                    if ($field->storage !== FieldStorageStrategy::Taxonomy) {
                        throw new LogicException('Taxonomy field storage mismatch: ' . $fieldKey);
                    }

                    $taxonomyKey = (string)($field->options['taxonomy'] ?? '');
                    $taxonomy = $taxonomies->definition($taxonomyKey);
                    if ($taxonomy === null || !$taxonomy->supports($contentType->identifier())) {
                        throw new LogicException(sprintf(
                            'Taxonomy field "%s" is not compatible with content type "%s".',
                            $fieldKey,
                            $contentType->identifier(),
                        ));
                    }
                }
            }

            foreach ($contentType->taxonomies as $taxonomyKey) {
                $taxonomy = $taxonomies->definition($taxonomyKey);
                if ($taxonomy === null) {
                    throw new LogicException(sprintf(
                        'Content type "%s" references unknown taxonomy "%s".',
                        $contentType->identifier(),
                        $taxonomyKey,
                    ));
                }

                if (!$taxonomy->supports($contentType->identifier())) {
                    throw new LogicException(sprintf(
                        'Taxonomy "%s" does not declare content type "%s".',
                        $taxonomyKey,
                        $contentType->identifier(),
                    ));
                }
            }
        }

        foreach ($taxonomies->definitions() as $taxonomy) {
            foreach ($taxonomy->contentTypes as $contentTypeKey) {
                if ($contentTypes->definition($contentTypeKey) === null) {
                    throw new LogicException(sprintf(
                        'Taxonomy "%s" references unknown content type "%s".',
                        $taxonomy->identifier(),
                        $contentTypeKey,
                    ));
                }
            }
        }
    }
}
