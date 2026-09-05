<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('content_fields')) {
            return;
        }

        $this->schema->create('content_fields', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('content_id');
            $table->string('field_key', 128);
            $table->string('value_type', 48);
            $table->longText('value_json')->nullable();
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamps();

            $table->unique(
                ['content_id', 'field_key'],
                'cms_content_field_unique'
            );
            $table->index('field_key', 'cms_content_field_key_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('content_fields');
    }
};
