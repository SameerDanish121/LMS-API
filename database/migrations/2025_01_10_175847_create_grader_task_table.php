<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('grader_task', function (Blueprint $table) {
            $table->integer('Task_id')->index('grader_task_ibfk_1');
            $table->integer('Grader_id')->index('grader_task_ibfk_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grader_task');
    }
};
