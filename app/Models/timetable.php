<?php

namespace App\Models;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class timetable extends Model
{
    // The table name is explicitly set to 'timetable'
    protected $table = 'timetable';

    // Disable timestamps if not present in the table (no created_at/updated_at columns)
    public $timestamps = false;

    // Define the fillable properties for mass assignment
    protected $fillable = [
        'session_id',
        'section_id',
        'dayslot_id',
        'venue_id',
        'course_id',
        'teacher_id',
        'junior_lecturer_id',
        'type'
    ];

    /**
     * Define relationships with other models
     */

    // Relationship to the Session mod
    public function session()
    {
        return $this->belongsTo(Session::class);
    }

    // Relationship to the Section model
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    // Relationship to the Dayslot model
    public function dayslot()
    {
        return $this->belongsTo(Dayslot::class);
    }

    // Relationship to the Venue model
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    // Relationship to the Course model
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    // Relationship to the Teacher model (nullable)
    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }

    // Relationship to the JuniorLecturer model (nullable)
    public function juniorLecturer()
    {
        return $this->belongsTo(JuniorLecturer::class, 'junior_lecturer_id');
    }
    public static function getTodayTimetableBySectionId($section_id)
    {
        if (!$section_id) {
            return [];
        }
        if (!(new session())->getCurrentSessionId()) {
            return [];
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:id,name'
        ])
            ->whereHas('dayslot', function ($query) {
                $query->where('day', Carbon::now()->format('l'));
            })
            ->where('section_id', $section_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'juniorlecturer' => $item->juniorLecturer ? $item->juniorLecturer->name : 'N/A',
                    'venue' => $item->venue->venue,
                    'day' => $item->dayslot->day,
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
        return $timetable;
    }
    public static function getTodayTimetableOfTeacherById($teacher_id = null)
    {
        if (!$teacher_id) {
            return [];
        }
        if (!(new session())->getCurrentSessionId()) {
            return [];
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:name',
            'section:id'
        ])
            ->whereHas('dayslot', function ($query) {
                $query->where('day', Carbon::now()->format('l'));
            })
            ->where('teacher_id', $teacher_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'coursename' => $item->course->name ?? 'N/A',
                    'description' => $item->course->description ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'juniorlecturer' => $item->juniorLecturer ? $item->juniorLecturer->name : 'N/A',
                    'venue' => $item->venue->venue ?? 'N/A',
                    'day' => $item->dayslot->day ?? 'N/A',
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
        return $timetable;
    }
    public static function getFullTimetableBySectionId($section_id = null)
    {

        if (!$section_id) {
            return [];
        }
        return Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:id,name'
        ])
            ->where('section_id', $section_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'juniorlecturer' => $item->juniorLecturer ? $item->juniorLecturer->name : 'N/A',
                    'venue' => $item->venue->venue,
                    'day' => $item->dayslot->day,
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
    }
    public static function getFullTimetableByTeacherId($teacher_id = null)
    {
        if (!$teacher_id) {
            return [];

        }
        return timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:name',
            'section'
        ])
            ->where('teacher_id', $teacher_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'venue' => $item->venue->venue,
                    'day' => $item->dayslot->day,
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
    }
}
