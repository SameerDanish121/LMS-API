<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class coursecontent_topic extends Model
{
    protected $table = 'coursecontent_topic';
    public $timestamps = false;
    protected $fillable = ['coursecontent_id', 'topic_id'];
    public function courseContent()
    {
        return $this->belongsTo(CourseContent::class, 'coursecontent_id', 'id');
    }
    public function topic()
    {
        return $this->belongsTo(Topic::class, 'topic_id', 'id');
    }
}
