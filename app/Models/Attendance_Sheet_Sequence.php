<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attendance_Sheet_Sequence extends Model
{
    protected $table = 'attendance_sheet_sequence';

    // Set primary keys for composite primary key
    protected $primaryKey = ['teacher_offered_course_id', 'student_id', 'For', 'SeatNumber'];

    // Indicate that the primary key is not auto-incrementing
    public $incrementing = false;

    // Disable timestamps as the table doesn't have created_at or updated_at columns
    public $timestamps = false;

    // Mass assignable attributes
    protected $fillable = [
        'teacher_offered_course_id',
        'student_id',
        'For',
        'SeatNumber',
    ];

    // Define relationships

    /**
     * Relationship with the TeacherOfferedCourse model.
     */
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id');
    }

    /**
     * Relationship with the Student model.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
