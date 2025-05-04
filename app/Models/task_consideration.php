<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class task_consideration extends Model
{
    protected $table = 'task_consideration';

    // Specify the primary key for the table
    protected $primaryKey = 'id';

    // Indicates if the primary key is auto-incrementing
    public $incrementing = true;

    // Specify the data type of the primary key
    protected $keyType = 'int';

    // Indicates if the model should be timestamped (created_at, updated_at)
    public $timestamps = false;

    // Define the attributes that are mass assignable
    protected $fillable = [
        'teacher_offered_course_id',
        'type',
        'top',
        'jl_consider_count',
    ];

    // Relationships

    /**
     * Get the teacher offered course associated with this task consideration.
     */
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id', 'id');
    }
    public static function getConsiderationSummary($teacher_offered_course_id, $isLab = false)
    {
        $considerations = self::where('teacher_offered_course_id', $teacher_offered_course_id)->get();
        $summary = [];
    
        foreach ($considerations as $item) {
            $type = $item->type;
            $top = (int) $item->top;
            $jl = $item->jl_consider_count !== null ? (int) $item->jl_consider_count : null;
    
            if ($isLab) {
                if($jl){
                    $summary[$type] = [
                        'Total'=> $top,
                        'Teacher' => $top - $jl,
                        'Junior Lecturer' => $jl,
                    ];
                }else{
                    $summary[$type] = [
                        'Total'=> $top,
                        'Teacher' => $top,
                        'Junior Lecturer' => $jl,
                    ];
                }
               
            } else {
                $summary[$type] = [
                    'Total'=> $top,
                    'Teacher' => $top,
                ];
            }
        }
    
        return $summary;
    }
    
    

}
