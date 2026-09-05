<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up(): void
    {
        if (
            $this->schema->hasTable('contents')
            && !$this->schema->hasIndex('contents', 'cms_content_site_cursor_index')
        ) {
            $this->schema->table('contents', function (Blueprint $table): void {
                $table->index(
                    ['site_id', 'id'],
                    'cms_content_site_cursor_index',
                );
            });
        }

        if (
            $this->schema->hasTable('content_relations')
            && !$this->schema->hasIndex('content_relations', 'cms_content_relation_hydration_index')
        ) {
            $this->schema->table('content_relations', function (Blueprint $table): void {
                $table->index(
                    ['source_content_id', 'field_key', 'sort_order'],
                    'cms_content_relation_hydration_index',
                );
            });
        }

        if (
            $this->schema->hasTable('content_terms')
            && !$this->schema->hasIndex('content_terms', 'cms_content_term_hydration_index')
        ) {
            $this->schema->table('content_terms', function (Blueprint $table): void {
                $table->index(
                    ['content_id', 'taxonomy', 'sort_order'],
                    'cms_content_term_hydration_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (
            $this->schema->hasTable('content_terms')
            && $this->schema->hasIndex('content_terms', 'cms_content_term_hydration_index')
        ) {
            $this->schema->table('content_terms', function (Blueprint $table): void {
                $table->dropIndex('cms_content_term_hydration_index');
            });
        }

        if (
            $this->schema->hasTable('content_relations')
            && $this->schema->hasIndex('content_relations', 'cms_content_relation_hydration_index')
        ) {
            $this->schema->table('content_relations', function (Blueprint $table): void {
                $table->dropIndex('cms_content_relation_hydration_index');
            });
        }

        if (
            $this->schema->hasTable('contents')
            && $this->schema->hasIndex('contents', 'cms_content_site_cursor_index')
        ) {
            $this->schema->table('contents', function (Blueprint $table): void {
                $table->dropIndex('cms_content_site_cursor_index');
            });
        }
    }
};
