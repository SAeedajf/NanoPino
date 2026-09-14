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
            && !$this->schema->hasIndex('contents', 'cms_content_status_cursor_index')
        ) {
            $this->schema->table('contents', function (Blueprint $table): void {
                $table->index(
                    ['site_id', 'status', 'locale', 'id'],
                    'cms_content_status_cursor_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (
            $this->schema->hasTable('contents')
            && $this->schema->hasIndex('contents', 'cms_content_status_cursor_index')
        ) {
            $this->schema->table('contents', function (Blueprint $table): void {
                $table->dropIndex('cms_content_status_cursor_index');
            });
        }
    }
};
