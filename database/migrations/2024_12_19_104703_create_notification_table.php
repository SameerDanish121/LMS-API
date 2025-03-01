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
        Schema::create('notification', function (Blueprint $table) {
            $table->integer('id', true);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->dateTime('notification_date')->nullable()->useCurrent();
            $table->string('sender', 50)->nullable()->default(null); // Changed from ENUM to VARCHAR(50)
            $table->string('reciever', 50)->nullable()->default(null); 
            $table->boolean('Brodcast')->nullable()->default(false);
            $table->integer('TL_sender_id')->nullable()->index('tl_sender_id');
            $table->integer('Student_Section')->nullable()->index('student_section');
            $table->integer('TL_receiver_id')->nullable()->index('tl_receiver_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification');
    }
};
