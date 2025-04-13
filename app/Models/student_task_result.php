<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class student_task_result extends Model
{
    protected $table = 'student_task_result';
    public $timestamps = false;
    protected $fillable = [
        'ObtainedMarks',
        'Task_id',
        'Student_id'
    ];

    /**
     * Define relationships with other models if necessary
     */

    // Relationship to Task model
    public function task()
    {
        return $this->belongsTo(Task::class, 'Task_id');
    }    public function student()
    {
        return $this->belongsTo(Student::class, 'Student_id');
    }
    public static function storeOrUpdateResult($task_id, $student_RegNo, $obtainedMarks)
    {
        $student = Student::where('RegNo', $student_RegNo)->first();
        if ($student) {
            $result = self::where('Task_id', $task_id)
                ->where('Student_id', $student->id)
                ->first();
    
            if ($result) {
                $result->update(['ObtainedMarks' => $obtainedMarks]);
            } else {
                $result = self::create([
                    'ObtainedMarks' => $obtainedMarks,
                    'Task_id' => $task_id,
                    'Student_id' => $student->id
                ]);
            }
    
            return $result;
        }
    
        return null; // Return null if student not found
    }
    
}
