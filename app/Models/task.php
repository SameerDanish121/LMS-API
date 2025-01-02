<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class task extends Model
{
    protected $table = 'task';
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'type',
        'path',
        'CreatedBy',
        'points',
        'start_date',
        'due_date',
        'title',
        'IsEvaluated',
        'teacher_offered_course_id',
        'isMarked',
    ];

    /**
     * Define relationships with other models
     */

    // Relationship to TeacherOfferedCourse model
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
}
