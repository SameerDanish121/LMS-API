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
        Schema::table('date_sheet', function (Blueprint $table) {
            $table->foreign(['section_id'], 'date_sheet_ibfk_1')->references(['id'])->on('section')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['course_id'], 'date_sheet_ibfk_2')->references(['id'])->on('course')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['session_id'], 'date_sheet_ibfk_3')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('date_sheet', function (Blueprint $table) {
            $table->dropForeign('date_sheet_ibfk_1');
            $table->dropForeign('date_sheet_ibfk_2');
            $table->dropForeign('date_sheet_ibfk_3');
        });
    }
};
