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
        Schema::create('t_coursecontent_topic_status', function (Blueprint $table) {
            $table->boolean('Status')->nullable()->default(false);
            $table->integer('coursecontent_id')->index('coursecontent_id');
            $table->integer('topic_id')->index('topic_id');
            $table->integer('teacher_offered_courses_id')->index('teacher_offered_courses_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('t_coursecontent_topic_status');
    }
};
