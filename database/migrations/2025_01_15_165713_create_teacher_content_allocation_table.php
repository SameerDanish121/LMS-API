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
        Schema::create('teacher_content_allocation', function (Blueprint $table) {
            $table->integer('id', true);
            $table->integer('content_id')->index('content_id');
            $table->integer('teacher_offered_course_id')->index('teacher_offered_course_id');
            $table->timestamp('date_time')->nullable()->useCurrent();
            $table->text('instructions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_content_allocation');
    }
};
