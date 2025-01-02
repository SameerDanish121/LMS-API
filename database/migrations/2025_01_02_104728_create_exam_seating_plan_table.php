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
        Schema::create('exam_seating_plan', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('RowNo');
            $table->integer('SeatNo');
            $table->date('Date');
            $table->time('Time');
            $table->enum('Exam', ['Mid', 'Final']);
            $table->integer('venue_id')->index('venue_id');
            $table->integer('student_id')->index('student_id');
            $table->integer('section_id')->index('section_id');
            $table->integer('course_id')->index('course_id');
            $table->integer('session_id')->index('fk_exam_seating_plan_session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_seating_plan');
    }
};
