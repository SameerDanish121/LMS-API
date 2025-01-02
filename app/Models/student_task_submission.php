<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class student_task_submission extends Model
{
    // The table name is explicitly set to 'student_task_submission'
    protected $table = 'student_task_submission';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'Answer',
        'DateTime',
        'Student_id',
        'Task_id'
    ];
    public function task()
    {
        return $this->belongsTo(Task::class, 'Task_id');
    }

    // Relationship to Student model
    public function student()
    {
        return $this->belongsTo(Student::class, 'Student_id');
    }
   
}
