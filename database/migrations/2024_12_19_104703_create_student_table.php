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
        Schema::create('student', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('RegNo', 20)->unique('regno');
            $table->string('name', 100);
            $table->decimal('cgpa', 3)->nullable();
            $table->string('gender', 10)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('guardian', 100)->nullable();
            $table->string('image', 100)->nullable();
            $table->integer('user_id')->nullable()->unique('user_id');
            $table->integer('section_id')->nullable()->index('section_id');
            $table->integer('program_id')->index('program_id');
            $table->integer('session_id')->nullable()->index('session_id');
            $table->enum('status', ['Graduate', 'UnderGraduate', 'Freeze'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student');
    }
};
