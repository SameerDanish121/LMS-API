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
        Schema::table('contested_attendance', function (Blueprint $table) {
            $table->foreign(['Attendance_id'], 'contested_attendance_ibfk_1')->references(['id'])->on('attendance')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contested_attendance', function (Blueprint $table) {
            $table->dropForeign('contested_attendance_ibfk_1');
        });
    }
};
