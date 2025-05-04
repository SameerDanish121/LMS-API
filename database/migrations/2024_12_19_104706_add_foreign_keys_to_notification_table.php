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
        Schema::table('notification', function (Blueprint $table) {
            $table->foreign(['Student_Section'], 'notification_ibfk_1')->references(['id'])->on('section')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['TL_sender_id'], 'notification_ibfk_2')->references(['id'])->on('user')->onUpdate('no action')->onDelete('set null');
            $table->foreign(['TL_receiver_id'], 'notification_ibfk_3')->references(['id'])->on('user')->onUpdate('no action')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notification', function (Blueprint $table) {
            $table->dropForeign('notification_ibfk_1');
            $table->dropForeign('notification_ibfk_2');
            $table->dropForeign('notification_ibfk_3');
        });
    }
};
