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
        Schema::table('student_offered_courses', function (Blueprint $table) {
            $table->foreign(['student_id'], 'student_offered_courses_ibfk_1')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['section_id'], 'student_offered_courses_ibfk_2')->references(['id'])->on('section')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['offered_course_id'], 'student_offered_courses_ibfk_3')->references(['id'])->on('offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_offered_courses', function (Blueprint $table) {
            $table->dropForeign('student_offered_courses_ibfk_1');
            $table->dropForeign('student_offered_courses_ibfk_2');
            $table->dropForeign('student_offered_courses_ibfk_3');
        });
    }
};
