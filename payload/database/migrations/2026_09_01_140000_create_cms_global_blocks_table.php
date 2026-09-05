<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('global_blocks')) {
            return;
        }

        $this->schema->create('global_blocks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('name', 190);
            $table->longText('document_json');
            $table->char('checksum', 64);
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('updated_at');

            $table->index(['site_id', 'updated_at'], 'cms_global_blocks_site_index');
            $table->index('checksum', 'cms_global_blocks_checksum_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('global_blocks');
    }
};
