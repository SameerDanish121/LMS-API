<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class quiz_questions extends Model
{
    protected $table = 'quiz_questions';
    protected $primaryKey = 'id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
        protected $fillable = [
        'question_no',
        'question_text',
        'points',
        'coursecontent_id',
    ];

    public function courseContent()
    {
        return $this->belongsTo(CourseContent::class, 'coursecontent_id', 'id');
    }
    public function Options()
    {
        return $this->hasMany(options::class, 'quiz_question_id', 'id');
    }
}
