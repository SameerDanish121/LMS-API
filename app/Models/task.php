<?php

namespace App\Models;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class task extends Model
{
    protected $table = 'task';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
    protected $fillable = [
        'type',
        'coursecontent_id',
        'CreatedBy',
        'points',
        'start_date',
        'due_date',
        'teacher_offered_course_id',
        'title',
        'isMarked',
    ];

    // Relationships

    /**
     * Get the course content associated with this task.
     */
    public function courseContent()
    {
        return $this->belongsTo(CourseContent::class, 'coursecontent_id', 'id');
    }

    /**
     * Get the teacher offered course associated with this task.
     */
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id');
    }
    public function getSectionIdByTaskId($taskId): ?int
    {
        // Retrieve the task and chain the relationships to get section_id
        $task = Task::with('teacherOfferedCourse.section') // Load teacherOfferedCourse and then section
            ->where('id', $taskId) // Filter by task_id
            ->first();
        // Safely access teacherOfferedCourse and section using optional chaining
        $sectionId = $task?->teacherOfferedCourse?->section?->id;

        // Return the section ID or null if not found
        return $sectionId ?? null;
    }
    public static function ChangeStatusOfTask(int $taskId): bool
    {
        try {
            // Find the task by ID
            $task = self::find($taskId);

            if ($task) {
                $task->isMarked = true;
                return $task->save();
            }
            return false;
        } catch (Exception $e) {
            // Handle any errors and return false
            return false;
        }
    }
  

}
