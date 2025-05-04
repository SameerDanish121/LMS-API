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
        Schema::table('timetable', function (Blueprint $table) {
            $table->foreign(['session_id'], 'timetable_ibfk_1')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['section_id'], 'timetable_ibfk_2')->references(['id'])->on('section')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['dayslot_id'], 'timetable_ibfk_3')->references(['id'])->on('dayslot')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['venue_id'], 'timetable_ibfk_4')->references(['id'])->on('venue')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['course_id'], 'timetable_ibfk_5')->references(['id'])->on('course')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['teacher_id'], 'timetable_ibfk_6')->references(['id'])->on('teacher')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['junior_lecturer_id'], 'timetable_ibfk_7')->references(['id'])->on('juniorlecturer')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timetable', function (Blueprint $table) {
            $table->dropForeign('timetable_ibfk_1');
            $table->dropForeign('timetable_ibfk_2');
            $table->dropForeign('timetable_ibfk_3');
            $table->dropForeign('timetable_ibfk_4');
            $table->dropForeign('timetable_ibfk_5');
            $table->dropForeign('timetable_ibfk_6');
            $table->dropForeign('timetable_ibfk_7');
        });
    }
};
