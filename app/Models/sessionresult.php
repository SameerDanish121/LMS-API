<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class sessionresult extends Model
{
    protected $table = 'sessionresult';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = ['GPA', 'Total_Credit_Hours', 'ObtainedCreditPoints', 'session_id', 'student_id'];
    public function session()
    {
        return $this->belongsTo(Session::class, 'session_id');
    }
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
