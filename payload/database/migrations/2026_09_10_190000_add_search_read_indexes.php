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
            $this->schema->hasTable('search_documents')
            && !$this->schema->hasIndex('search_documents', 'cms_search_site_updated_index')
        ) {
            $this->schema->table('search_documents', function (Blueprint $table): void {
                $table->index(
                    ['site_id', 'updated_at', 'id'],
                    'cms_search_site_updated_index',
                );
            });
        }

        if (
            $this->schema->hasTable('search_documents')
            && !$this->schema->hasIndex('search_documents', 'cms_search_scope_updated_index')
        ) {
            $this->schema->table('search_documents', function (Blueprint $table): void {
                $table->index(
                    ['site_id', 'document_type', 'locale', 'updated_at', 'id'],
                    'cms_search_scope_updated_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (
            $this->schema->hasTable('search_documents')
            && $this->schema->hasIndex('search_documents', 'cms_search_scope_updated_index')
        ) {
            $this->schema->table('search_documents', function (Blueprint $table): void {
                $table->dropIndex('cms_search_scope_updated_index');
            });
        }

        if (
            $this->schema->hasTable('search_documents')
            && $this->schema->hasIndex('search_documents', 'cms_search_site_updated_index')
        ) {
            $this->schema->table('search_documents', function (Blueprint $table): void {
                $table->dropIndex('cms_search_site_updated_index');
            });
        }
    }
};
