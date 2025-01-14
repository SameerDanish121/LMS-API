<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class options extends Model
{
    protected $table = 'options';
    protected $primaryKey = 'option_id';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false;
        protected $fillable = [
        'quiz_question_id',
        'option_text',
        'is_correct',
    ];
    public function quizQuestion()
    {
        return $this->belongsTo(quiz_questions::class, 'quiz_question_id', 'id');
    }
}
