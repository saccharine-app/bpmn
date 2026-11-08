<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_instances')) {
            Schema::create('process_instances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_version_id')->constrained()->cascadeOnDelete();
                $table->enum('status', ['running', 'suspended', 'halted', 'completed', 'failed'])->default('running');
                $table->string('durable_workflow_id')->index(); 
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_instances');
    }
};