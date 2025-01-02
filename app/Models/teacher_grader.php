<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher_grader extends Model
{
   // The table name is explicitly set to 'teacher_grader'
   protected $table = 'teacher_grader';

   // Disable timestamps if not present in the table (no created_at/updated_at columns)
   public $timestamps = false;

   // Define the fillable properties for mass assignment
   protected $fillable = [
       'grader_id', 'teacher_id', 'session_id', 'feedback'
   ];

   /**
    * Define relationships with other models
    */

   // Relationship to Grader model
   public function grader()
   {
       return $this->belongsTo(Grader::class, 'grader_id');
   }
   public function teacher()
   {
       return $this->belongsTo(Teacher::class, 'teacher_id');
   }
   public function session()
   {
       return $this->belongsTo(Session::class, 'session_id');
   }
}
