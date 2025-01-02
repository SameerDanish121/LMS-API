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
        Schema::create('sessionresult', function (Blueprint $table) {
            $table->integer('id', true);
            $table->decimal('GPA', 3)->nullable();
            $table->integer('Total_Credit_Hours');
            $table->integer('ObtainedCreditPoints');
            $table->integer('session_id')->index('session_id');
            $table->integer('student_id')->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessionresult');
    }
};
