<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('type', ['Quiz', 'Assignment', 'LabTask']);
            $table->string('path')->nullable();
            $table->enum('CreatedBy', ['Teacher', 'Junior Lecturer'])->default('Teacher');
            $table->integer('points');
            $table->datetime('start_date');
            $table->datetime('due_date');
            $table->string('title')->nullable();
            $table->boolean('IsEvaluated')->nullable()->default(true);
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->boolean('isMarked')->default(false); // Added 'isMarked' column
        
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task');
    }
};
