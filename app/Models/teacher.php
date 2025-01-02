<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher extends Model
{
   // The table name is explicitly set to 'teacher'
   protected $table = 'teacher';
   public $timestamps = false;
   protected $fillable = [
       'user_id', 'name', 'image', 'date_of_birth', 'gender'
   ];
   public function user()
   {
       return $this->belongsTo(User::class, 'user_id');
   }
}
