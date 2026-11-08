<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_history_events')) {
            Schema::create('process_history_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
                $table->string('bpmn_element_id');
                $table->string('node_type')->nullable();
                $table->string('status')->default('completed'); // 'completed', 'failed', 'timeout'
                $table->json('state_snapshot')->nullable();      // Snapshot of $userData at completion
                $table->timestamp('completed_at')->useCurrent();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_history_events');
    }
};