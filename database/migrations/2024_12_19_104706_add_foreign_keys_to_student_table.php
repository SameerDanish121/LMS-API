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
        Schema::table('student', function (Blueprint $table) {
            $table->foreign(['user_id'], 'student_ibfk_1')->references(['id'])->on('user')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['section_id'], 'student_ibfk_2')->references(['id'])->on('section')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['program_id'], 'student_ibfk_3')->references(['id'])->on('program')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['session_id'], 'student_ibfk_4')->references(['id'])->on('session')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student', function (Blueprint $table) {
            $table->dropForeign('student_ibfk_1');
            $table->dropForeign('student_ibfk_2');
            $table->dropForeign('student_ibfk_3');
            $table->dropForeign('student_ibfk_4');
        });
    }
};
