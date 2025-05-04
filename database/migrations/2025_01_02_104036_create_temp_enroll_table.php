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
        Schema::create('temp_enroll', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('RegNo', 50);
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->dateTime('date_time');
            $table->integer('venue')->index('venue');
            $table->boolean('isLab')->nullable()->default(false);
            $table->enum('status', ['p', 'a']);
            $table->enum('Request_Status', ['Accepted', 'Rejected'])->nullable();
            $table->boolean('isVerified')->nullable()->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temp_enroll');
    }
};
