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
        Schema::create('task', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('type', ['Quiz', 'Assignment', 'LabTask']);
            $table->integer('coursecontent_id')->index('task_ibfk_2');
            $table->enum('CreatedBy', ['Teacher', 'Junior Lecturer'])->default('Teacher');
            $table->integer('points');
            $table->dateTime('start_date');
            $table->dateTime('due_date');
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->string('title')->nullable();
            $table->boolean('isMarked')->default(false);
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
