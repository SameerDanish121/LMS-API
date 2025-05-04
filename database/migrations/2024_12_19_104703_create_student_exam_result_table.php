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
        Schema::create('student_exam_result', function (Blueprint $table) {
            $table->integer('obtained_marks');
            $table->integer('question_id');
            $table->integer('student_id')->index('student_id');
            $table->integer('exam_id')->index('exam_id');

            $table->primary(['question_id', 'student_id', 'exam_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_exam_result');
    }
};
