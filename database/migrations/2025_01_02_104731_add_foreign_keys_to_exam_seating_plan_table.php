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
        Schema::table('exam_seating_plan', function (Blueprint $table) {
            $table->foreign(['venue_id'], 'exam_seating_plan_ibfk_1')->references(['id'])->on('venue')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'exam_seating_plan_ibfk_2')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['section_id'], 'exam_seating_plan_ibfk_3')->references(['id'])->on('section')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['course_id'], 'exam_seating_plan_ibfk_4')->references(['id'])->on('course')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['session_id'], 'fk_exam_seating_plan_session_id')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_seating_plan', function (Blueprint $table) {
            $table->dropForeign('exam_seating_plan_ibfk_1');
            $table->dropForeign('exam_seating_plan_ibfk_2');
            $table->dropForeign('exam_seating_plan_ibfk_3');
            $table->dropForeign('exam_seating_plan_ibfk_4');
            $table->dropForeign('fk_exam_seating_plan_session_id');
        });
    }
};
