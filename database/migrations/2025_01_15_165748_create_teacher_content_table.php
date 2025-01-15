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
        Schema::create('teacher_content', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('teacher_id')->index('teacher_id');
            $table->string('type', 50);
            $table->string('title');
            $table->string('path');
            $table->text('description')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_content');
    }
};
