<?php
declare(strict_types=1);

use Pinoox\Component\Migration\Migrator;
use Pinoox\Component\Package\Lifecycle\AppLifecycleContext;
use Pinoox\Portal\Database\DB;

return [
    'uninstall' => static function (AppLifecycleContext $context): void {
        $package = $context->package;
        if ($package !== 'com_pinoox_cms') {
            throw new RuntimeException('Unexpected package in NanoPino uninstall lifecycle.');
        }

        (new Migrator(
            $package,
            'rollback',
            [
                'force' => true,
                'use_transactions' => true,
            ],
        ))->rollback(0);

        $schema = DB::schema(DB::connectionNameForPackage($package));
        $ownedTables = [
            'settings',
            'audit_events',
            'contents',
            'content_fields',
            'content_relations',
            'terms',
            'content_terms',
            'content_revisions',
            'media_assets',
            'media_usages',
            'media_variants',
            'theme_previews',
            'builder_documents',
            'builder_revisions',
            'global_blocks',
            'search_documents',
        ];

        $remaining = [];
        foreach ($ownedTables as $table) {
            if ($schema->hasTable($table)) {
                $remaining[] = $table;
            }
        }

        if ($remaining !== []) {
            throw new RuntimeException(
                'NanoPino uninstall migration rollback left owned tables in place: '
                . implode(', ', $remaining)
                . '. Refusing to delete application files.',
            );
        }
    },
];
