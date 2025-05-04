<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $table = 'course'; // Explicit table name
    public $timestamps = false; // Disable timestamps
    protected $primaryKey = 'id'; // Primary key column

    // Define fillable fields for mass assignment
    protected $fillable = [
        'code',
        'name',
        'credit_hours',
        'pre_req_main',
        'program_id',
        'type',
        'description',
        'lab',
    ];

    /**
     * Define the relationship with the Program model.
     */
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id', 'id');
    }
    // In Section.php
    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'course_id', 'id');
    }

    /**
     * Define the self-referential relationship for the 'pre_req_main' field (prerequisite course).
     */
    public function prerequisite()
    {
        return $this->belongsTo(Course::class, 'pre_req_main', 'id');
    }

    /**
     * Get the ID of a course by its name.
     *
     * @param string|null $Name
     * @return int|null
     */
    public function getIDByName($Name = null)
    {
        if (!$Name) {
            return null; // Return null if no name is provided
        }

        // Use 'value' to directly retrieve the 'id' of the matching record
        $ID = self::where('name', $Name)->value('id');

        return $ID ?: null; // Return the ID if found, otherwise null
    }
}
