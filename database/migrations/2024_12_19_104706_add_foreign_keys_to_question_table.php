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
        Schema::table('question', function (Blueprint $table) {
            $table->foreign(['exam_id'], 'question_ibfk_1')->references(['id'])->on('exam')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('question', function (Blueprint $table) {
            $table->dropForeign('question_ibfk_1');
        });
    }
};
