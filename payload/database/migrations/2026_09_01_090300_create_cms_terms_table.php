<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('terms')) {
            return;
        }

        $this->schema->create('terms', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('taxonomy', 64);
            $table->string('name', 255);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('locale', 16)->default('fa');
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['site_id', 'taxonomy', 'locale', 'slug'],
                'cms_term_slug_unique'
            );
            $table->index(
                ['site_id', 'taxonomy', 'locale', 'parent_id'],
                'cms_term_tree_index'
            );
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('terms');
    }
};
