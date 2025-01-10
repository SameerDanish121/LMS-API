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
        Schema::table('grader_task', function (Blueprint $table) {
            $table->foreign(['Task_id'], 'grader_task_ibfk_1')->references(['id'])->on('task')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['Grader_id'], 'grader_task_ibfk_2')->references(['id'])->on('grader')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('grader_task', function (Blueprint $table) {
            $table->dropForeign('grader_task_ibfk_1');
            $table->dropForeign('grader_task_ibfk_2');
        });
    }
};
