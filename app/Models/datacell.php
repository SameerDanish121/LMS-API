<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class datacell extends Model
{
 // The table name is explicitly set to 'datacell'
 protected $table = 'datacell';

 // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
 public $timestamps = false;

 // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
 protected $primaryKey = 'id';

 // Define which fields are mass assignable
 protected $fillable = [
     'user_id',
     'name',
     'phone_number',
     'Designation',
     'image'
 ];

 // Define the relationship with the User model
 public function user()
 {
     return $this->belongsTo(User::class, 'user_id', 'id');
 }
}
