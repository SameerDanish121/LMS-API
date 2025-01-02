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
        Schema::table('sessionresult', function (Blueprint $table) {
            $table->foreign(['session_id'], 'sessionresult_ibfk_1')->references(['id'])->on('session')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['student_id'], 'sessionresult_ibfk_2')->references(['id'])->on('student')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sessionresult', function (Blueprint $table) {
            $table->dropForeign('sessionresult_ibfk_1');
            $table->dropForeign('sessionresult_ibfk_2');
        });
    }
};
