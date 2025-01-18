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
        Schema::table('course', function (Blueprint $table) {
            $table->foreign(['pre_req_main'], 'course_ibfk_1')->references(['id'])->on('course')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['program_id'], 'course_ibfk_2')->references(['id'])->on('program')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course', function (Blueprint $table) {
            $table->dropForeign('course_ibfk_1');
            $table->dropForeign('course_ibfk_2');
        });
    }
};
