<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Inspector;

use App\com_pinoox_cms\Cms\Block\BlockDefinition;
use App\com_pinoox_cms\Cms\Block\BlockRegistry;

final readonly class BlockInspectorSchemaFactory
{
    public function __construct(private BlockRegistry $blocks) {}

    public function for(string $blockType): BlockInspectorSchema
    {
        $definition = $this->blocks->definition($blockType)
            ?? throw new \RuntimeException('Block not registered: ' . $blockType);

        $fields = [];
        foreach ($definition->attributes as $attribute) {
            $fields[] = new InspectorField(
                $attribute->name,
                $attribute->type->value,
                $this->label($attribute->name),
                $attribute->required,
                $attribute->default,
                $attribute->rules,
                false,
            );
        }

        $styleGroups = [];
        foreach (['color', 'typography', 'spacing', 'dimensions', 'align', 'visibility'] as $group) {
            if (($definition->supports[$group] ?? false) === true) {
                $styleGroups[] = $group;
            }
        }

        return new BlockInspectorSchema(
            $definition->name,
            $definition->title,
            $definition->schemaVersion,
            $fields,
            $styleGroups,
            (bool)($definition->supports['responsive'] ?? false),
            $definition->slots,
        );
    }

    private function label(string $key): string
    {
        return ucfirst((string)preg_replace('/([a-z])([A-Z])/', '$1 $2', $key));
    }
}
