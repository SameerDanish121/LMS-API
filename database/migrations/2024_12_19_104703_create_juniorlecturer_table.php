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
        Schema::create('juniorlecturer', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->nullable()->unique('user_id');
            $table->string('name', 100);
            $table->string('image', 50)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('cnic', 15)->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juniorlecturer');
    }
};
