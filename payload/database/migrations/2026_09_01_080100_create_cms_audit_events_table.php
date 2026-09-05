<?php
declare(strict_types=1);

namespace App\com_pinoox_cms\Migration;

use Illuminate\Database\Schema\Blueprint;
use Pinoox\Component\Migration\MigrationBase;

return new class extends MigrationBase
{
    public function up()
    {
        if ($this->schema->hasTable('audit_events')) {
            return;
        }

        $this->schema->create('audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('event_id', 64)->unique();
            $table->string('action', 191);
            $table->string('owner', 191);
            $table->string('outcome', 24);
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('scope_type', 32);
            $table->string('scope_id', 191)->default('');
            $table->string('target_type', 64)->nullable();
            $table->string('target_id', 191)->nullable();
            $table->string('correlation_id', 96)->nullable();
            $table->longText('metadata_json');
            $table->timestamp('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['action', 'occurred_at'], 'cms_audit_action_time_index');
            $table->index(['actor_id', 'occurred_at'], 'cms_audit_actor_time_index');
            $table->index(['target_type', 'target_id'], 'cms_audit_target_index');
            $table->index('correlation_id', 'cms_audit_correlation_index');
        });
    }

    public function down(): void
    {
        $this->schema->dropIfExists('audit_events');
    }
};
