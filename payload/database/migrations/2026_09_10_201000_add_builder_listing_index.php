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
            $this->schema->hasTable('builder_documents')
            && !$this->schema->hasIndex('builder_documents', 'cms_builder_target_list_index')
        ) {
            $this->schema->table('builder_documents', function (Blueprint $table): void {
                $table->index(
                    ['site_id', 'target_type', 'target_key', 'locale', 'id'],
                    'cms_builder_target_list_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (
            $this->schema->hasTable('builder_documents')
            && $this->schema->hasIndex('builder_documents', 'cms_builder_target_list_index')
        ) {
            $this->schema->table('builder_documents', function (Blueprint $table): void {
                $table->dropIndex('cms_builder_target_list_index');
            });
        }
    }
};
