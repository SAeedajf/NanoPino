<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('search_documents')) return;

        $this->schema->create('search_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id');
            $table->string('document_type',64);
            $table->string('document_id',96);
            $table->string('locale',16);
            $table->string('title',1000)->default('');
            $table->longText('search_text');
            $table->string('url',2048)->nullable();
            $table->longText('metadata_json');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['site_id','document_type','document_id','locale'],
                'cms_search_doc_unique'
            );
            $table->index(
                ['site_id','document_type','locale'],
                'cms_search_scope_index'
            );
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('search_documents');
    }
};
