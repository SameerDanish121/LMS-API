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
        Schema::create('hod', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('name', 100);
            $table->string('designation', 100);
            $table->string('image')->nullable();
            $table->string('department', 100);
            $table->integer('user_id')->nullable()->index('fk_hod_user');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hod');
    }
};
