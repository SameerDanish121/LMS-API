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
        Schema::table('coursecontent_topic', function (Blueprint $table) {
            $table->foreign(['coursecontent_id'], 'coursecontent_topic_ibfk_1')->references(['id'])->on('coursecontent')->onUpdate('no action')->onDelete('cascade');
            $table->foreign(['topic_id'], 'coursecontent_topic_ibfk_2')->references(['id'])->on('topic')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coursecontent_topic', function (Blueprint $table) {
            $table->dropForeign('coursecontent_topic_ibfk_1');
            $table->dropForeign('coursecontent_topic_ibfk_2');
        });
    }
};
