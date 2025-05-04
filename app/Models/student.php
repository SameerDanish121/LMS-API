<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class student extends Model
{
    protected $table = 'student';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'RegNo', 'name', 'cgpa', 'gender', 'date_of_birth', 
        'guardian', 'image', 'user_id', 'section_id', 'program_id', 
        'session_id', 'status'
    ];

    /**
     * Define relationships with other models if necessary
     */

    // Relationship to User model
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relationship to Section model
    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    // Relationship to Program model
    public function program()
    {
        return $this->belongsTo(Program::class, 'program_id');
    }

    // Relationship to Session model
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
    public function studentOfferedCourses()
    {
        return $this->hasMany(student_offered_courses::class, 'student_id');
    }
}
