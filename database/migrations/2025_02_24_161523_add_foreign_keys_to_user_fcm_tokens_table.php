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
        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->foreign(['user_id'], 'user_fcm_tokens_ibfk_1')->references(['id'])->on('user')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_fcm_tokens', function (Blueprint $table) {
            $table->dropForeign('user_fcm_tokens_ibfk_1');
        });
    }
};
