<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class admin extends Model
{
    protected $table = 'admin';
    
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
        'image',
    ];

    public function user()
    {
        // Foreign key 'user_id' in this table, referencing 'id' in the 'user' table
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
