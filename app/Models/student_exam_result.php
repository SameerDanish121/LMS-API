<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class student_exam_result extends Model
{
    protected $table = 'student_exam_result';
    public $timestamps = false; 
    protected $primaryKey = null; 
    public $incrementing = false; 
    protected $fillable = ['obtained_marks', 'question_id', 'student_id', 'exam_id'];
    public function exam()
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }
    public function question()
    {
        return $this->belongsTo(Question::class, 'question_id');
    }

    // Relationship to Student model
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

}
