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
        Schema::table('student_task_submission', function (Blueprint $table) {
            $table->foreign(['Task_id'], 'student_task_submission_ibfk_1')->references(['id'])->on('task')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['Student_id'], 'student_task_submission_ibfk_2')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_task_submission', function (Blueprint $table) {
            $table->dropForeign('student_task_submission_ibfk_1');
            $table->dropForeign('student_task_submission_ibfk_2');
        });
    }
};
