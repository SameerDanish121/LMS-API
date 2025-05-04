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
        Schema::table('teacher_offered_courses', function (Blueprint $table) {
            $table->foreign(['teacher_id'], 'teacher_offered_courses_ibfk_1')->references(['id'])->on('teacher')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['section_id'], 'teacher_offered_courses_ibfk_2')->references(['id'])->on('section')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['offered_course_id'], 'teacher_offered_courses_ibfk_3')->references(['id'])->on('offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_offered_courses', function (Blueprint $table) {
            $table->dropForeign('teacher_offered_courses_ibfk_1');
            $table->dropForeign('teacher_offered_courses_ibfk_2');
            $table->dropForeign('teacher_offered_courses_ibfk_3');
        });
    }
};
