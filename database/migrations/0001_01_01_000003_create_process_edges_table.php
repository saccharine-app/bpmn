<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_edges')) {
            Schema::create('process_edges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_version_id')->constrained()->cascadeOnDelete();
                $table->string('bpmn_element_id');
                $table->string('source_node_id');
                $table->string('target_node_id');
                $table->text('condition_expression')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_edges');
    }
};