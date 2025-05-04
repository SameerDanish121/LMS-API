<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excluded_days', function (Blueprint $table) {
            $table->integer('id', true);
            $table->date('date');
            $table->enum('type', ['Holiday', 'Reschedule','Exam']);
            $table->string('reason')->nullable();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('excluded_days');
    }
};
