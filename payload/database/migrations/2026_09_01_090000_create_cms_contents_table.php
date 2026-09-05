<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('contents')) {
            return;
        }

        $this->schema->create('contents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('type', 64);
            $table->string('status', 32)->default('draft');
            $table->string('title', 255);
            $table->string('slug', 160);
            $table->text('excerpt')->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('locale', 16)->default('fa');
            $table->longText('document')->nullable();
            $table->longText('metadata')->nullable();
            $table->unsignedBigInteger('revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['site_id', 'type', 'locale', 'slug'],
                'cms_content_slug_unique'
            );
            $table->index(
                ['site_id', 'type', 'status', 'locale'],
                'cms_content_list_index'
            );
            $table->index(
                ['site_id', 'author_id', 'status'],
                'cms_content_author_index'
            );
            $table->index(
                ['site_id', 'parent_id'],
                'cms_content_parent_index'
            );
            $table->index(
                ['status', 'scheduled_at'],
                'cms_content_schedule_index'
            );
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('contents');
    }
};
