<?php

namespace App\Models;

use Exception;
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
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    // Relationship to OfferedCourse model
    public function offeredCourse()
    {
        return $this->belongsTo(offered_courses::class, 'offered_course_id');
    }
    public static function GetCountOfTotalEnrollments($student_id = null)
    {
        if (!$student_id) {
            return 0;
        }
        $currentSession = (new session())->getCurrentSessionId();
        if ($currentSession == 0) {
            return $currentSession;
        }
        $count = self::where('student_id', $student_id)
            ->whereHas('offeredCourse', function ($query) use ($currentSession) {
                $query->where('session_id', $currentSession);
            })->count();
        return $count;
    }
}
