<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\offered_courses;

class coursecontent extends Model
{
    protected $table = 'coursecontent';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = [
        'type',
        'content',
        'week',
        'title',
        'offered_course_id',
    ];
    public function offeredCourse()
    {
        return $this->belongsTo(offered_courses::class, 'offered_course_id', 'id');
    }
    public function tasks()
    {
        return $this->hasMany(Task::class, 'coursecontent_id', 'id');
    }
}
