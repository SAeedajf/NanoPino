<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('theme_previews')) return;

        $this->schema->create('theme_previews', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('token_hash', 64)->unique();
            $table->unsignedBigInteger('site_id')->default(1);
            $table->string('package', 128);
            $table->string('theme_name', 128);
            $table->string('context', 96)->nullable();
            $table->string('variation', 96)->nullable();
            $table->longText('design_overrides')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('created_at');
            $table->timestamp('revoked_at')->nullable();

            $table->index(['site_id', 'expires_at'], 'cms_theme_preview_site_expiry_index');
            $table->index(['package', 'theme_name'], 'cms_theme_preview_theme_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('theme_previews');
    }
};
