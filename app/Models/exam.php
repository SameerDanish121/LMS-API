<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
USE App\Models\offered_courses;
class exam extends Model
{
    // The table name is explicitly set to 'exam'
    protected $table = 'exam';

    // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define which fields are mass assignable
    protected $fillable = [
        'type',
        'total_marks',
        'Solid_marks',
        'QuestionPaper',
        'offered_course_id'
    ];

    // Define the relationship to the OfferedCourse model
    public function offeredCourse()
    {
        return $this->belongsTo(offered_courses::class, 'offered_course_id', 'id');
    }
    public function questions()
    {
        return $this->hasMany(question::class, 'exam_id', 'id');
    }
}
