<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\offered_courses;

class student_offered_courses extends Model
{
    protected $table = 'student_offered_courses';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = ['grade', 'attempt_no', 'student_id', 'section_id', 'offered_course_id'];

    /**
     * Define relationships with other models if necessary
     */

    // Relationship to Student model
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
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
    
}
