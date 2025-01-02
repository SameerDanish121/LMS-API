<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
class excluded_days extends Model
{
    protected $table = 'excluded_days';

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
        'date',
        'type',
        'reason',
    ];
    public static function checkHoliday()
    {
        // Get today's date
        $today = Carbon::today()->toDateString();

        // Check if a record exists with today's date and type 'Holiday'
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Holiday')
            ->exists();

        return $isHoliday;
    }
    public static function checkHolidayReason()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Holiday')
            ->first();
        return $isHoliday?"Today is a holiday. Reason: " . $isHoliday->reason . ". There will be no classes as per the schedule.":$isHoliday;
    }
}
