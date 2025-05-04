<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class date_sheet extends Model
{
    protected $table = 'date_sheet';

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
        'Day',
        'Date',
        'Start_Time',
        'End_Time',
        'Type',
        'section_id',
        'course_id',
        'session_id',
    ];

    // Define relationships

    /**
     * Relationship with the Section model.
     * A date sheet belongs to a section.
     */
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * Relationship with the Course model.
     * A date sheet belongs to a course.
     */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    /**
     * Relationship with the Session model.
     * A date sheet belongs to a session.
     */
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
}
