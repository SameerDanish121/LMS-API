<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class juniorlecturer extends Model
{
    protected $table = 'juniorlecturer';

    // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define which fields are mass assignable
    protected $fillable = [
        'user_id',
        'name',
        'image',
        'date_of_birth',
        'gender',
    ];

    // Define the relationship to the User model
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
