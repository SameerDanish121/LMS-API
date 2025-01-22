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
        Schema::table('task_consideration', function (Blueprint $table) {
            $table->foreign(['teacher_offered_course_id'], 'task_consideration_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_consideration', function (Blueprint $table) {
            $table->dropForeign('task_consideration_ibfk_1');
        });
    }
};
