<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class contested_attendance extends Model
{
    protected $table = 'contested_attendance';

    // Set the primary key for the table
    protected $primaryKey = 'id';

    // Specify that the primary key is auto-incrementing
    public $incrementing = true;

    // Set the data type of the primary key
    protected $keyType = 'integer';

    // Disable timestamps (if the table does not have `created_at` and `updated_at` columns)
    public $timestamps = false;

    // Mass assignable attributes
    protected $fillable = [
        'Attendance_id',
        'Status',
        'isResolved',
    ];

    // Define relationships

    /**
     * Relationship with the Attendance model.
     * A contested attendance belongs to an attendance record.
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class, 'Attendance_id');
    }
}
