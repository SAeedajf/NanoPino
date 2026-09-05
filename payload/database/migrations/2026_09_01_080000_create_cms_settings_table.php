<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('settings')) {
            return;
        }

        $this->schema->create('settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('setting_key', 191);
            $table->string('scope_type', 32);
            $table->string('scope_id', 191)->default('');
            $table->string('value_type', 32);
            $table->longText('value_json');
            $table->unsignedBigInteger('version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(
                ['setting_key', 'scope_type', 'scope_id'],
                'cms_settings_scope_key_unique'
            );
            $table->index(['scope_type', 'scope_id'], 'cms_settings_scope_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('settings');
    }
};
