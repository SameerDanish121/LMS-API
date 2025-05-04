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
        Schema::table('director', function (Blueprint $table) {
            $table->foreign(['user_id'], 'fk_director_user')->references(['id'])->on('user')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('director', function (Blueprint $table) {
            $table->dropForeign('fk_director_user');
        });
    }
};
