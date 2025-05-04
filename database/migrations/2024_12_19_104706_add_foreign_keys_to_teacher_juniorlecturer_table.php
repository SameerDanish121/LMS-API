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
        Schema::table('teacher_juniorlecturer', function (Blueprint $table) {
            $table->foreign(['juniorlecturer_id'], 'teacher_juniorlecturer_ibfk_1')->references(['id'])->on('juniorlecturer')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['teacher_offered_course_id'], 'teacher_juniorlecturer_ibfk_2')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_juniorlecturer', function (Blueprint $table) {
            $table->dropForeign('teacher_juniorlecturer_ibfk_1');
            $table->dropForeign('teacher_juniorlecturer_ibfk_2');
        });
    }
};
