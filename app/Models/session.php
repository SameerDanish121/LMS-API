<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\offered_courses;
use Carbon\Carbon;


class session extends Model
{
    protected $table = 'session';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = ['name', 'year', 'start_date', 'end_date'];
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
        $name = $session->name.'-'.$session->year;
        return $name ? $name : 'XYZ-0000';
    }
    public function getSessionIdByName($Name = null): int
    {
        if (!$Name) {
            return 0;
        }
        $split = explode('-', $Name);
        if (count($split) !== 2) {
            return 0; // Return 0 if the format is invalid
        }
        $name = $split[0];
        $year = $split[1];

        // Query the session table
        $session = self::where('name', $name)
            ->where('year', $year)
            ->value('id'); // Directly fetch the 'id'

        // Return the session ID or 0 if not found
        return $session ?: 0;
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

}
