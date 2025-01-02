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
        Schema::table('subjectresult', function (Blueprint $table) {
            $table->foreign(['student_offered_course_id'], 'subjectresult_ibfk_1')->references(['id'])->on('student_offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subjectresult', function (Blueprint $table) {
            $table->dropForeign('subjectresult_ibfk_1');
        });
    }
};
