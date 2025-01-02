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
        Schema::create('teacher_offered_courses', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('teacher_id')->index('teacher_id');
            $table->integer('section_id')->index('section_id');
            $table->integer('offered_course_id')->index('offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_offered_courses');
    }
};
