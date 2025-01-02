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
        Schema::table('student_exam_result', function (Blueprint $table) {
            $table->foreign(['exam_id'], 'student_exam_result_ibfk_1')->references(['id'])->on('exam')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['question_id'], 'student_exam_result_ibfk_2')->references(['id'])->on('question')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'student_exam_result_ibfk_3')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_exam_result', function (Blueprint $table) {
            $table->dropForeign('student_exam_result_ibfk_1');
            $table->dropForeign('student_exam_result_ibfk_2');
            $table->dropForeign('student_exam_result_ibfk_3');
        });
    }
};
