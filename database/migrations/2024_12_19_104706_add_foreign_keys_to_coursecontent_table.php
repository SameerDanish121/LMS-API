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
        Schema::table('coursecontent', function (Blueprint $table) {
            $table->foreign(['offered_course_id'], 'coursecontent_ibfk_1')->references(['id'])->on('offered_courses')->onUpdate('no action')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coursecontent', function (Blueprint $table) {
            $table->dropForeign('coursecontent_ibfk_1');
        });
    }
};
