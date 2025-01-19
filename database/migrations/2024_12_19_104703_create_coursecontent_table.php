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
        Schema::create('coursecontent', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('type', 50);
            $table->string('content')->nullable();
            $table->integer('week')->nullable();
            $table->string('title', 100);
            $table->integer('offered_course_id')->index('offered_course_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coursecontent');
    }
};
