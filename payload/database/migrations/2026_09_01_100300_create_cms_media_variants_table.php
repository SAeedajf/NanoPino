<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('media_variants')) return;

        $this->schema->create('media_variants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('media_id');
            $table->string('variant_key', 128);
            $table->unsignedBigInteger('native_file_id');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('mime', 127)->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamp('created_at');

            $table->unique(['media_id', 'variant_key'], 'cms_media_variant_unique');
            $table->unique('native_file_id', 'cms_media_variant_native_unique');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('media_variants');
    }
};
