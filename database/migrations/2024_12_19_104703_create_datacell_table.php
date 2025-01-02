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
        Schema::create('datacell', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('user_id')->nullable()->unique('user_id');
            $table->string('name', 100);
            $table->string('phone_number', 15)->nullable();
            $table->string('Designation', 50)->nullable();
            $table->string('image', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datacell');
    }
};
