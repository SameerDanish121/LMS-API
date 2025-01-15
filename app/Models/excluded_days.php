<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
class excluded_days extends Model
{
    protected $table = 'excluded_days';
    protected $primaryKey = 'id';
        public $incrementing = true;
    protected $keyType = 'integer';
    public $timestamps = false;

    protected $fillable = [
        'date',
        'type',
        'reason',
    ];
    public static function checkHoliday()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Holiday')
            ->exists();
        return $isHoliday;
    }
    public static function checkHolidayReason()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', operator: $today)
            ->where('type', 'Holiday')
            ->first();
        return $isHoliday?"Today is a holiday. Reason: " . $isHoliday->reason . ". There will be no classes as per the schedule.":$isHoliday;
    }
    public static function checkReschedule()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Reschedule')
            ->exists();
        return $isHoliday;
    }
}
