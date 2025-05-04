<?php

namespace App\Models;
use App\Models\offered_courses;
use Illuminate\Database\Eloquent\Model;

class subjectresult extends Model
{
        // The table name is explicitly set to 'subjectresult'
        protected $table = 'subjectresult';

        // Disable timestamps if not present in the table (no created_at/updated_at columns)
        public $timestamps = false;
    
        // Define the fillable properties for mass assignment
        protected $fillable = [
            'grade', 'mid', 'final', 'internal', 'lab', 'quality_points', 'student_offered_course_id'
        ];
    
        /**
         * Define relationships with other models if necessary
         */
    
        // Relationship to StudentOfferedCourse model
        public function studentOfferedCourse()
        {
            return $this->belongsTo(student_offered_courses::class, 'student_offered_course_id');
        }
    
}
