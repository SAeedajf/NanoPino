<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('media_usages')) return;

        $this->schema->create('media_usages', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('media_id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('resource_type', 128);
            $table->string('resource_id', 191);
            $table->string('context', 128);
            $table->timestamp('created_at');

            $table->unique(
                ['media_id', 'resource_type', 'resource_id', 'context'],
                'cms_media_usage_unique'
            );
            $table->index(
                ['resource_type', 'resource_id', 'context'],
                'cms_media_resource_usage_index'
            );
            $table->index(['site_id', 'media_id'], 'cms_media_usage_site_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('media_usages');
    }
};
