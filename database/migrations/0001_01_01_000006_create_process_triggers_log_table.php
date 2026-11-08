<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_triggers_log')) {
            Schema::create('process_triggers_log', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_version_id')->constrained()->cascadeOnDelete();
                $table->string('business_key');
                $table->string('durable_workflow_id');
                $table->timestamps();

                // Prevent the same version from running the same business key twice
                $table->unique(['process_version_id', 'business_key']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_triggers_log');
    }
};