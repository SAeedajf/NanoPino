<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('media_assets')) return;

        $this->schema->create('media_assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->unsignedBigInteger('native_file_id');
            $table->string('native_hash_id', 96)->nullable();
            $table->string('kind', 24);
            $table->string('mime', 127);
            $table->string('original_name', 255);
            $table->string('title', 255)->default('');
            $table->string('alt', 500)->default('');
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('duration', 12, 3)->nullable();
            $table->decimal('focal_x', 8, 6)->nullable();
            $table->decimal('focal_y', 8, 6)->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('status', 24)->default('ready');
            $table->text('url')->nullable();
            $table->text('thumb')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->unique('native_file_id', 'cms_media_native_file_unique');
            $table->index(['site_id', 'kind', 'status', 'id'], 'cms_media_list_index');
            $table->index(['site_id', 'owner_id', 'status'], 'cms_media_owner_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('media_assets');
    }
};
