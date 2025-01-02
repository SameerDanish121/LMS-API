<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class grader extends Model
{
     // The table name is explicitly set to 'grader'
     protected $table = 'grader';

     // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
     public $timestamps = false;
 
     // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
     protected $primaryKey = 'id';
 
     // Define which fields are mass assignable
     protected $fillable = [
         'student_id',
         'type',
         'status'
     ];
      public function student()
     {
        return $this->belongsTo(Student::class, 'student_id', 'id');
     }
}
