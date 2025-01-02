<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class temp_enroll extends Model
{
    protected $table = 'temp_enroll';

    // Set the primary key for the table
    protected $primaryKey = 'id';

    // Specify that the primary key is not auto-incrementing (since it's an integer)
    public $incrementing = true;

    // Set the data type of the primary key
    protected $keyType = 'integer';

    // Disable timestamps (if the table does not have `created_at` and `updated_at` columns)
    public $timestamps = false;

    // Mass assignable attributes
    protected $fillable = [
        'RegNo',
        'teacher_offered_course_id',
        'date_time',
        'venue',
        'isLab',
        'status',
        'Request_Status',
        'isVerified',
    ];

    // Define relationships

    /**
     * Relationship with the TeacherOfferedCourse model.
     * A temp_enroll record belongs to a teacher-offered course.
     */
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id');
    }

    /**
     * Relationship with the Venue model.
     * A temp_enroll record belongs to a venue.
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue');
    }
}
