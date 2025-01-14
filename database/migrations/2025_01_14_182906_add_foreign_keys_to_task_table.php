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
        Schema::table('task', function (Blueprint $table) {
            $table->foreign(['teacher_offered_course_id'], 'task_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('no action');
            $table->foreign(['coursecontent_id'], 'task_ibfk_2')->references(['id'])->on('coursecontent')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task', function (Blueprint $table) {
            $table->dropForeign('task_ibfk_1');
            $table->dropForeign('task_ibfk_2');
        });
    }
};
