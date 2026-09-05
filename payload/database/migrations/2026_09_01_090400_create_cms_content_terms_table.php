<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('content_terms')) {
            return;
        }

        $this->schema->create('content_terms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('content_id');
            $table->unsignedBigInteger('term_id');
            $table->string('taxonomy', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['content_id', 'term_id', 'taxonomy'],
                'cms_content_term_unique'
            );
            $table->index(
                ['term_id', 'taxonomy'],
                'cms_content_term_lookup'
            );
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('content_terms');
    }
};
