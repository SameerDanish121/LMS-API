<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher_content_allocation extends Model
{
    protected $table = 'teacher_content_allocation';

    // Disable timestamps if not using `created_at` and `updated_at`
    public $timestamps = false;

    // Specify the fillable fields
    protected $fillable = [
        'content_id',
        'teacher_offered_course_id',
        'date_time',
        'instructions',
    ];

    /**
     * Define the relationship with the TeacherContent model.
     */
    public function content()
    {
        return $this->belongsTo(teacher_content::class, 'content_id', 'id');
    }
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id', 'id');
    }
}
