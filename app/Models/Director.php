<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Director extends Model
{
    protected $table = 'director'; // Specify the table name if not plural

    protected $primaryKey = 'id';
    protected $fillable = [
        'name',
        'designation',
        'image',
        'user_id',
    ];

    // Define the relationship with User
    public function user()
    {
        // Foreign key 'user_id' in this table, referencing 'id' in the 'user' table
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
