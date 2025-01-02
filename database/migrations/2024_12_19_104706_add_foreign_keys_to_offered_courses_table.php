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
        Schema::table('offered_courses', function (Blueprint $table) {
            $table->foreign(['course_id'], 'offered_courses_ibfk_1')->references(['id'])->on('course')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['session_id'], 'offered_courses_ibfk_2')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offered_courses', function (Blueprint $table) {
            $table->dropForeign('offered_courses_ibfk_1');
            $table->dropForeign('offered_courses_ibfk_2');
        });
    }
};
