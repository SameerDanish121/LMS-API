<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class task_consideration extends Model
{
    protected $table = 'task_consideration';

    // Specify the primary key for the table
    protected $primaryKey = 'id';

    // Indicates if the primary key is auto-incrementing
    public $incrementing = true;

    // Specify the data type of the primary key
    protected $keyType = 'int';

    // Indicates if the model should be timestamped (created_at, updated_at)
    public $timestamps = false;

    // Define the attributes that are mass assignable
    protected $fillable = [
        'teacher_offered_course_id',
        'type',
        'top',
        'jl_consider_count',
    ];

    // Relationships

    /**
     * Get the teacher offered course associated with this task consideration.
     */
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id', 'id');
    }
}
