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
        Schema::table('temp_enroll', function (Blueprint $table) {
            $table->foreign(['teacher_offered_course_id'], 'temp_enroll_ibfk_1')->references(['id'])->on('teacher_offered_courses')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['venue'], 'temp_enroll_ibfk_2')->references(['id'])->on('venue')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temp_enroll', function (Blueprint $table) {
            $table->dropForeign('temp_enroll_ibfk_1');
            $table->dropForeign('temp_enroll_ibfk_2');
        });
    }
};
