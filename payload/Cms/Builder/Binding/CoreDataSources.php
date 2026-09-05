<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Cms\Builder\Binding;

final class CoreDataSources
{
    public const OWNER = 'cms.core';

    public static function register(DataSourceRegistry $registry): void
    {
        foreach ([
            ['content', 'Content', ['content.read']],
            ['media', 'Media', ['media.read']],
            ['author', 'Author', ['content.read']],
            ['field', 'Custom Field', ['content.read']],
            ['taxonomy', 'Taxonomy', ['taxonomy.read']],
            ['setting', 'Setting', ['settings.read']],
            ['user', 'User', ['users.read']],
            ['query', 'Query', ['content.read']],
        ] as [$id, $label, $capabilities]) {
            $registry->register(new DataSourceDefinition(
                $id,
                self::OWNER,
                $label,
                true,
                $capabilities,
            ));
        }
    }
}
