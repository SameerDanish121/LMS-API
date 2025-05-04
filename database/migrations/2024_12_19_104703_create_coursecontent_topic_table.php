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
        Schema::create('coursecontent_topic', function (Blueprint $table) {
            $table->integer('coursecontent_id')->index('coursecontent_id');
            $table->integer('topic_id')->index('topic_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coursecontent_topic');
    }
};
