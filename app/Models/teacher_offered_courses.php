<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher_offered_courses extends Model
{
   // The table name is explicitly set to 'teacher_offered_courses'
   protected $table = 'teacher_offered_courses';

   // Disable timestamps if not present in the table (no created_at/updated_at columns)
   public $timestamps = false;
   protected $primaryKey='id';

   // Define the fillable properties for mass assignment
   protected $fillable = [
       'teacher_id', 'section_id', 'offered_course_id'
   ];

   /**
    * Define relationships with other models
    */

   // Relationship to Teacher model
   public function teacher()
   {
       return $this->belongsTo(Teacher::class, 'teacher_id');
   }

   // Relationship to Section model
   public function section()
   {
       return $this->belongsTo(Section::class, 'section_id');
   }

   // Relationship to OfferedCourse model
   public function offeredCourse()
   {
       return $this->belongsTo(offered_courses::class, 'offered_course_id');
   }
   public function tasks()
    {
        return $this->hasMany(Task::class, 'teacher_offered_course_id', 'id');
    }
    public function teacherJuniorLecturer()
    {
        return $this->hasOne(teacher_juniorlecturer::class, 'teacher_offered_course_id');
    }
}
