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
        Schema::table('attendance_sheet_sequence', function (Blueprint $table) {
            $table->foreign(['teacher_offered_course_id'], 'attendance_sheet_sequence_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'attendance_sheet_sequence_ibfk_2')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_sheet_sequence', function (Blueprint $table) {
            $table->dropForeign('attendance_sheet_sequence_ibfk_1');
            $table->dropForeign('attendance_sheet_sequence_ibfk_2');
        });
    }
};
