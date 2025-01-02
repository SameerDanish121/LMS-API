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
        Schema::table('t_coursecontent_topic_status', function (Blueprint $table) {
            $table->foreign(['teacher_offered_courses_id'], 't_coursecontent_topic_status_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['coursecontent_id'], 't_coursecontent_topic_status_ibfk_2')->references(['id'])->on('coursecontent')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['topic_id'], 't_coursecontent_topic_status_ibfk_3')->references(['id'])->on('topic')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('t_coursecontent_topic_status', function (Blueprint $table) {
            $table->dropForeign('t_coursecontent_topic_status_ibfk_1');
            $table->dropForeign('t_coursecontent_topic_status_ibfk_2');
            $table->dropForeign('t_coursecontent_topic_status_ibfk_3');
        });
    }
};
