<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_nodes')) {
            Schema::create('process_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_version_id')->constrained()->cascadeOnDelete();
                
                // Track elements that belong to an inline sub-process scope
                $table->string('parent_element_id')->nullable();

                $table->string('bpmn_element_id');
                $table->string('type');
                $table->string('name')->nullable();
                $table->string('implementation')->nullable();
                
                // The ID of the task this event sits on (e.g., 'Task_Invoice')
                $table->string('attached_to_element_id')->nullable();

                // The specific sub-type (e.g., 'error', 'timer', 'message')
                $table->string('event_definition_type')->nullable();
                
                // Preserve node input parameters to merge with user data
                $table->json('input_parameters')->nullable();
                
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_nodes');
    }
};