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
        Schema::create('course', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('code', 20)->unique('code');
            $table->string('name', 100);
            $table->integer('credit_hours');
            $table->integer('pre_req_main')->nullable()->index('pre_req_main');
            $table->integer('program_id')->nullable()->index('course_ibfk_2');
            $table->string('type', 50)->nullable();
            $table->string('description')->nullable();
            $table->boolean('lab');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course');
    }
};
