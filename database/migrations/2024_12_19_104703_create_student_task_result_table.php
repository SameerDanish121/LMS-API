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
        Schema::create('student_task_result', function (Blueprint $table) {
            $table->integer('ObtainedMarks');
            $table->integer('Task_id')->index('task_id');
            $table->integer('Student_id')->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_task_result');
    }
};
