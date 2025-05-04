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
        Schema::create('task_consideration', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->enum('type', ['Quiz', 'Assignment', 'LabTask'])->nullable();
            $table->integer('top')->default(3);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_consideration');
    }
};
