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
        Schema::table('attendance', function (Blueprint $table) {
            $table->foreign(['teacher_offered_course_id'], 'attendance_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'attendance_ibfk_2')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['venue_id'], 'fk_venue_id')->references(['id'])->on('venue')->onUpdate('cascade')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropForeign('attendance_ibfk_1');
            $table->dropForeign('attendance_ibfk_2');
            $table->dropForeign('fk_venue_id');
        });
    }
};
