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
        Schema::create('director', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('designation', 100);
            $table->string('image')->nullable();
            $table->integer('user_id')->nullable()->index('fk_director_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('director');
    }
};
