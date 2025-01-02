<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher_juniorlecturer extends Model
{
   // The table name is explicitly set to 'teacher_juniorlecturer'
   protected $table = 'teacher_juniorlecturer';

   // Disable timestamps if not present in the table (no created_at/updated_at columns)
   public $timestamps = false;

   // Define the fillable properties for mass assignment
   protected $fillable = [
       'juniorlecturer_id', 'teacher_offered_course_id'
   ];

   /**
    * Define relationships with other models
    */

   // Relationship to JuniorLecturer model
   public function juniorLecturer()
   {
       return $this->belongsTo(JuniorLecturer::class, 'juniorlecturer_id');
   }

   // Relationship to TeacherOfferedCourse model
   public function teacherOfferedCourse()
   {
       return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id');
   }
}
