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
        Schema::create('exam', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('type', ['Mid', 'Final']);
            $table->integer('total_marks');
            $table->integer('Solid_marks');
            $table->string('QuestionPaper')->nullable();
            $table->integer('offered_course_id')->index('offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam');
    }
};
