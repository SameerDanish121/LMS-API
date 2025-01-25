<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class t_coursecontent_topic_status extends Model
{
    // The table name is explicitly set to 't_coursecontent_topic_status'
    protected $table = 't_coursecontent_topic_status';
    protected $primaryKey = null;
    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'Status', 'coursecontent_id', 'topic_id', 'teacher_offered_courses_id'
    ];

    /**
     * Define relationships with other models
     */

    // Relationship to TeacherOfferedCourse model
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_courses_id');
    }

    // Relationship to CourseContent model
    public function courseContent()
    {
        return $this->belongsTo(CourseContent::class, 'coursecontent_id');
    }

    // Relationship to Topic model
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id');
    }
}
