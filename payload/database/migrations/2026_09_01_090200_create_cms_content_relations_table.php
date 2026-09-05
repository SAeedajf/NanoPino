<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('content_relations')) {
            return;
        }

        $this->schema->create('content_relations', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('source_content_id');
            $table->string('field_key', 128);
            $table->unsignedBigInteger('target_content_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['source_content_id', 'field_key', 'target_content_id'],
                'cms_content_relation_unique'
            );
            $table->index(
                ['target_content_id', 'field_key'],
                'cms_content_relation_target_index'
            );
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('content_relations');
    }
};
