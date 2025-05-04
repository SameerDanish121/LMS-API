<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class dayslot extends Model
{
    // The table name is explicitly set to 'dayslot'
    protected $table = 'dayslot';

    // Disable timestamps as the table doesn't seem to have created_at/updated_at columns
    public $timestamps = false;

    // Specify the primary key if it's different from 'id' (but here it's 'id' by default)
    protected $primaryKey = 'id';

    // Define which fields are mass assignable
    protected $fillable = [
        'day',
        'start_time',
        'end_time'
    ];
    // In Section.php
    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'dayslot_id', 'id');
    }


}
