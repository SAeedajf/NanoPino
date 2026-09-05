<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if (!$this->schema->hasTable('builder_documents')) {
            $this->schema->create('builder_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('site_id')->default(1);
                $table->string('target_type', 32);
                $table->string('target_key', 190);
                $table->string('locale', 16)->default('fa');
                $table->string('status', 32)->default('draft');
                $table->longText('document_json');
                $table->char('checksum', 64);
                $table->unsignedInteger('version')->default(1);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('created_at');
                $table->timestamp('updated_at');

                $table->unique(
                    ['site_id', 'target_type', 'target_key', 'locale'],
                    'cms_builder_target_unique'
                );
                $table->index(
                    ['site_id', 'status', 'updated_at'],
                    'cms_builder_site_status_index'
                );
            });
        }

        if (!$this->schema->hasTable('builder_revisions')) {
            $this->schema->create('builder_revisions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('builder_id');
                $table->string('kind', 32);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->unsignedBigInteger('source_revision_id')->nullable();
                $table->longText('document_json');
                $table->char('checksum', 64);
                $table->timestamp('created_at');

                $table->index(
                    ['builder_id', 'kind', 'id'],
                    'cms_builder_revision_kind_index'
                );
                $table->index(
                    ['builder_id', 'actor_id', 'kind', 'id'],
                    'cms_builder_autosave_index'
                );
            });
        }
    }

    public function down(): void
    {
        $this->schema->dropIfExists('builder_revisions');
        $this->schema->dropIfExists('builder_documents');
    }
};
