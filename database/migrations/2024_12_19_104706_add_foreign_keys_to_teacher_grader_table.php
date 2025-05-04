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
        Schema::table('teacher_grader', function (Blueprint $table) {
            $table->foreign(['grader_id'], 'teacher_grader_ibfk_1')->references(['id'])->on('grader')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['teacher_id'], 'teacher_grader_ibfk_2')->references(['id'])->on('teacher')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['session_id'], 'teacher_grader_ibfk_3')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_grader', function (Blueprint $table) {
            $table->dropForeign('teacher_grader_ibfk_1');
            $table->dropForeign('teacher_grader_ibfk_2');
            $table->dropForeign('teacher_grader_ibfk_3');
        });
    }
};
