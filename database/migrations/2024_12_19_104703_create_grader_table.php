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
        Schema::create('grader', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('student_id')->nullable()->unique('student_id');
            $table->enum('type', ['need-based', 'merit'])->nullable();
            $table->enum('status', ['active', 'in-active'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grader');
    }
};
