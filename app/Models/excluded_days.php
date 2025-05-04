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
        $isHoliday = excluded_days::where('date', $today)
            ->where(function ($query) {
                $query->where('type', 'Holiday');
            })
            ->first();

        if ($isHoliday) {
            if ($isHoliday->type === 'Exam') {
                return "Today is an exam day. Reason: " . $isHoliday->reason . ". There will be no regular classes.";
            } elseif ($isHoliday->type === 'Holiday') {
                return "Today is a holiday. Reason: " . $isHoliday->reason . ". There will be no classes as per the schedule.";
            }
        }
        return $isHoliday;
    }
    public static function checkReschedule()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Reschedule')
            ->exists();
        return $isHoliday;
    }
    public static function checkRescheduleDay()
    {
        $today = Carbon::today()->toDateString();
        $isHoliday = excluded_days::where('date', $today)
            ->where('type', 'Reschedule')
            ->first()->reason;
        return $isHoliday;
    }
    public static function checkReasonOfReschedule()
    {
        $today = Carbon::today()->toDateString();
        $isReschedule = excluded_days::where('date', $today)
            ->where('type', 'Reschedule')
            ->first();

        return $isReschedule
            ? "Today the classes of Day: " . $isReschedule->reason . " will be rescheduled! Please follow the above timetable of " . $isReschedule->reason . " to attend classes."
            : $isReschedule;
    }
}
