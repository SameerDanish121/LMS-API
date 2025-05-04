<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class teacher extends Model
{
    // The table name is explicitly set to 'teacher'
    protected $table = 'teacher';
    protected $primaryKey = 'id'; // Fixed here: primaryKey should be a string
    public $timestamps = false;

    // Fillable fields for mass assignment
    protected $fillable = [
        'user_id', 
        'name', 
        'image', 
        'date_of_birth', 
        'gender',
        'cnic'
    ];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'teacher_id', 'id');
    }
    // Function to get ID by name
    public function getIDByName($Name = null)
    {
        if (!$Name) {
            return null;
        }

        // Use 'first' to retrieve the first matching record
        $record = self::where('name', $Name)->select('id')->first();
        return $record ? $record->id : null;
    }
    
}
