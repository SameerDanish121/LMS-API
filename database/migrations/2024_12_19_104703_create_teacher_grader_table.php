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
        Schema::create('teacher_grader', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('grader_id')->index('grader_id');
            $table->integer('teacher_id')->index('teacher_id');
            $table->integer('session_id')->index('session_id');
            $table->text('feedback')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_grader');
    }
};
