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
        Schema::create('attendance_sheet_sequence', function (Blueprint $table) {
            $table->integer('teacher_offered_course_id');
            $table->integer('student_id')->index('student_id');
            $table->enum('For', ['Class', 'Lab']);
            $table->integer('SeatNumber');

            $table->primary(['teacher_offered_course_id', 'student_id', 'For', 'SeatNumber']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_sheet_sequence');
    }
};
