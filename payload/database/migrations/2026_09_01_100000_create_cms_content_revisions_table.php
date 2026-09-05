<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('content_revisions')) {
            return;
        }

        $this->schema->create('content_revisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('content_type', 64);
            $table->string('kind', 32);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->char('checksum', 64);
            $table->unsignedBigInteger('source_revision_id')->nullable();
            $table->longText('payload_json');
            $table->timestamp('created_at');

            $table->index(
                ['content_id', 'kind', 'id'],
                'cms_revision_content_kind_index'
            );
            $table->index(
                ['content_id', 'actor_id', 'kind', 'id'],
                'cms_revision_autosave_index'
            );
            $table->index('checksum', 'cms_revision_checksum_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('content_revisions');
    }
};
