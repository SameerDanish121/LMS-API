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
        Schema::create('attendance', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('status', ['p', 'a']);
            $table->dateTime('date_time');
            $table->boolean('isLab');
            $table->integer('student_id')->index('student_id');
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->integer('venue_id')->nullable()->index('venue_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
