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
        Schema::table('teacher_content_allocation', function (Blueprint $table) {
            $table->foreign(['content_id'], 'teacher_content_allocation_ibfk_1')->references(['id'])->on('teacher_content')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['teacher_offered_course_id'], 'teacher_content_allocation_ibfk_2')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teacher_content_allocation', function (Blueprint $table) {
            $table->dropForeign('teacher_content_allocation_ibfk_1');
            $table->dropForeign('teacher_content_allocation_ibfk_2');
        });
    }
};
