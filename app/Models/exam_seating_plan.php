<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class exam_seating_plan extends Model
{
    protected $table = 'exam_seating_plan';

    // Set the primary key for the table
    protected $primaryKey = 'id';

    // Specify that the primary key is auto-incrementing
    public $incrementing = true;

    // Set the data type of the primary key
    protected $keyType = 'integer';

    // Disable timestamps (if the table does not have `created_at` and `updated_at` columns)
    public $timestamps = false;

    // Mass assignable attributes
    protected $fillable = [
        'RowNo',
        'SeatNo',
        'Date',
        'Time',
        'Exam',
        'venue_id',
        'student_id',
        'section_id',
        'course_id',
        'session_id',
    ];

    // Define relationships

    /**
     * Relationship with the Venue model.
     * An exam seating plan belongs to a venue.
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }

    /**
     * Relationship with the Student model.
     * An exam seating plan belongs to a student.
     */
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    /**
     * Relationship with the Section model.
     * An exam seating plan belongs to a section.
     */
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Relationship with the Course model.
     * An exam seating plan belongs to a course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Relationship with the Session model.
     * An exam seating plan belongs to a session.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
