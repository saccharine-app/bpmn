<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('process_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_instance_id')->constrained()->cascadeOnDelete();
            
            // Tracks which specific coroutine (master or child stub) owns this token
            $table->string('durable_workflow_id')->index(); 
            
            // The active node this token is currently sitting on
            $table->string('bpmn_element_id'); 
            
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('process_tokens');
    }
};