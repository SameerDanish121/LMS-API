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
        Schema::create('date_sheet', function (Blueprint $table) {
            $table->integer('id', true);
            $table->enum('Day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']);
            $table->date('Date');
            $table->time('Start_Time');
            $table->time('End_Time');
            $table->enum('Type', ['Mid', 'Final']);
            $table->integer('section_id')->index('section_id');
            $table->integer('course_id')->index('course_id');
            $table->integer('session_id')->index('session_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('date_sheet');
    }
};
