<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class grader_task extends Model
{
    protected $table = 'grader_task';

    // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define which fields are mass assignable
    protected $fillable = [
        'Task_id',
        'Grader_id',
    ];
    public function grader()
    {
        return $this->belongsTo(Grader::class, 'Grader_id', 'id');
    }
    public function task()
    {
        return $this->belongsTo(Task::class, 'Task_id', 'id');
    }
    public static function markTaskAsGraded($taskId)
    {
        $graderTask = self::where('Task_id', $taskId)->first();
        if ($graderTask) {
            
           $new=grader_task::where('Task_id', $taskId)->update(['isMarked' => true]);
            return $new;
        }
        return null;
    }

}
