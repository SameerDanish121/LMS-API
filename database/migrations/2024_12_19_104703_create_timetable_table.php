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
        Schema::create('timetable', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('session_id')->index('session_id');
            $table->integer('section_id')->index('section_id');
            $table->integer('dayslot_id')->index('dayslot_id');
            $table->integer('venue_id')->index('venue_id');
            $table->integer('course_id')->index('course_id');
            $table->integer('teacher_id')->nullable()->index('teacher_id');
            $table->integer('junior_lecturer_id')->nullable()->index('junior_lecturer_id');
            $table->enum('type', ['Class', 'Lab', 'Supervised Lab']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable');
    }
};
