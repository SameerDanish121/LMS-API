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
        Schema::create('teacher_juniorlecturer', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('juniorlecturer_id')->index('juniorlecturer_id');
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_juniorlecturer');
    }
};
