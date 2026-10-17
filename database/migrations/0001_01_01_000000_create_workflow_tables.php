<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('workflow_definitions')) {
            Schema::create('workflow_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('workflow_versions')) {
            Schema::create('workflow_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_definition_id')->constrained()->cascadeOnDelete();
                $table->integer('version');
                $table->longText('bpmn_xml');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('workflow_nodes')) {
            Schema::create('workflow_nodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
                $table->string('bpmn_element_id');
                $table->string('type');
                $table->string('name')->nullable();
                $table->string('implementation')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('workflow_edges')) {
            Schema::create('workflow_edges', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_version_id')->constrained()->cascadeOnDelete();
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
        Schema::dropIfExists('workflow_edges');
        Schema::dropIfExists('workflow_nodes');
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflow_definitions');
    }
};