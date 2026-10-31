<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('process_versions')) {
            Schema::create('process_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('process_definition_id')->constrained()->cascadeOnDelete();
                $table->integer('version');
                $table->longText('bpmn_xml');
                $table->boolean('is_active')->default(false);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('process_versions');
    }
};