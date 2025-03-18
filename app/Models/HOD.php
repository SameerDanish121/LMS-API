<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hod extends Model
{
    protected $table = 'hod';  // Specify table name since it's singular
    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'designation',
        'image',
        'department',
        'user_id',
    ];

    // Define relationship with User model
    public function user()
    {
        // Foreign key 'user_id' in this table, referencing 'id' in the 'user' table
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
