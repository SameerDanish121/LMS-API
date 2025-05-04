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
        Schema::create('subjectresult', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('grade', 2);
            $table->float('mid');
            $table->float('final');
            $table->float('internal');
            $table->float('lab');
            $table->float('quality_points');
            $table->integer('student_offered_course_id')->index('student_offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjectresult');
    }
};
