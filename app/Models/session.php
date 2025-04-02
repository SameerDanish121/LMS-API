<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Models\offered_courses;
use Carbon\Carbon;


class session extends Model
{
    protected $table = 'session';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'year', 'start_date', 'end_date'];
    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'session_id', 'id');
    }
    public function offeredCourses()
    {
        return $this->hasMany(offered_courses::class);
    }
    public function getCurrentSessionId(): int
    {
        $currentDate = Carbon::now()->toDateString();
        $session = self::where('start_date', '<=', $currentDate)
            ->where('end_date', '>=', $currentDate)
            ->first();
        return $session ? $session->id : 0;
    }
    public function getSessionNameByID($id = null): string
    {
        $session = self::find($id);
        $name = $session->name . '-' . $session->year;
        return $name ? $name : null;
    }
    public function getSessionIdByName($Name = null): int
    {
        if (!$Name) {
            return 0;
        }
        $split = explode('-', $Name);
        if (count($split) !== 2) {
            return 0;
        }
        $name = $split[0];
        $year = $split[1];
        $session = session::where('name', $name)
            ->where('year', $year)
            ->first();
        return $session->id ?: 0;
    }
    public function getUpcomingSessionId(): int
    {
        // Get the current date
        $currentDate = Carbon::now();

        // Get all sessions where the start_date is in the future
        $upcomingSessions = self::where('start_date', '>', $currentDate)->get();

        // If there are no upcoming sessions, return 0
        if ($upcomingSessions->isEmpty()) {
            return 0;
        }

        // Find the upcoming session with the least number of days left
        $upcomingSession = $upcomingSessions->reduce(function ($carry, $session) use ($currentDate) {
            // Calculate the days left for the current session
            $daysLeft = $currentDate->diffInDays(Carbon::parse($session->start_date), false);

            // If carry is null or this session has fewer days left, set it as the new carry
            if (is_null($carry) || $daysLeft < $carry['days_left']) {
                return [
                    'id' => $session->id,
                    'days_left' => $daysLeft
                ];
            }

            return $carry;
        });

        // Return the ID of the session with the least days left
        return $upcomingSession ? $upcomingSession['id'] : 0;
    }
    public static function getCurrentSessionWeek()
    {
        try {
            $currentSessionId = (new self)->getCurrentSessionId();
            if ($currentSessionId === 0) {
                return null;
            }
            $currentSession = self::find($currentSessionId);
            if (!$currentSession || !$currentSession->start_date) {
                return null;
            }
            $startDate = Carbon::parse($currentSession->start_date);
            $currentDate = Carbon::now();
            $daysDifference = $startDate->diffInDays($currentDate, false);
            $weekNumber = (int) floor($daysDifference / 7) + 1;
            if ($weekNumber <= 0) {
                return null;
            }
            if ($weekNumber >= 9) {
                $weekNumber--;
            }
            if ($weekNumber >= 11) {
                $weekNumber--;
            }
            return $weekNumber <= 16 ? $weekNumber : null;
        } catch (Exception $e) {
            return null;
        }
    }


}
