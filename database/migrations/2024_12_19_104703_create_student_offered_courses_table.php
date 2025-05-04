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
        Schema::create('student_offered_courses', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('grade', 5)->nullable();
            $table->integer('attempt_no')->nullable()->default(0);
            $table->integer('student_id')->index('student_id');
            $table->integer('section_id')->index('section_id');
            $table->integer('offered_course_id')->index('offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_offered_courses');
    }
};
