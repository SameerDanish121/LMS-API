<?php

namespace App\Models;
use Carbon\Carbon;
use Exception;
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
    public static function getTodayTimetableBySectionId($section_id, $day = null)
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
                    'start_time' => $item->dayslot->start_time ? $item->dayslot->start_time : null,
                    'end_time' => $item->dayslot->end_time ? $item->dayslot->end_time : null,
                ];
            });
        return $timetable;
    }
    public static function getTodayTimetableOfTeacherById($teacher_id = null, $day = null)
    {
        if (!$teacher_id) {
            return [];
        }
        if ((new session())->getCurrentSessionId() == 0) {
            return [];
        }
        $today = Carbon::now()->format('l');
        if ($day) {
            $today = $day;
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:name',
            'section:id'
        ])

            ->whereHas('dayslot', function ($query) use ($today) {
                $query->where('day', $today);
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
                    'start_time' => $item->dayslot->start_time ? $item->dayslot->start_time : null,
                    'end_time' => $item->dayslot->end_time ? $item->dayslot->end_time : null,
                ];
            })->sortBy(function ($item) {
                // Convert start_time to a sortable format
                $time = Carbon::createFromFormat('H:i:s', $item['start_time']);
                return $time->hour < 7 ? $time->hour + 12 : $time->hour;
            })
            ->values();
        return $timetable;
    }
    public static function getFullTimetableBySectionId($section_id = null)
    {
        if (!$section_id) {
            return [];
        }
        $Timetable = Timetable::with([
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
        return $Timetable;
    }
    public static function getFullTimetableByTeacherId($teacher_id = null)
    {
        if (!$teacher_id) {
            return [];
        }

        $timetable = timetable::with([
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
                    'day' => $item->dayslot->day,
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'venue' => $item->venue->venue,
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
        $groupedByDay = $timetable->groupBy('day')->map(function ($items, $day) {
            return [
                'day' => $day,
                'schedule' => $items->toArray()
            ];
        });

        return $groupedByDay->values()->toArray();
    }

    public static function getTodayTimetableOfJuniorLecturerById($JuniorLecturer_id = null, $day = null)
    {
        if (!$JuniorLecturer_id) {
            return [];
        }
        if (!(new session())->getCurrentSessionId()) {
            return [];
        }
        $today = Carbon::now()->format('l');
        if ($day) {
            $today = $day;
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'section:id'
        ])
            ->whereHas('dayslot', function ($query) use ($today) {
                $query->where('day', $today);
            })
            ->where('junior_lecturer_id', $JuniorLecturer_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'coursename' => $item->course->name ?? 'N/A',
                    'description' => $item->course->description ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'Teacher' => $item->teacher ? $item->teacher->name : 'N/A',
                    'venue' => $item->venue->venue ?? 'N/A',
                    'day' => $item->dayslot->day ?? 'N/A',
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
        return $timetable;
    }

    public static function getTodayTimetableOfEnrollementsByStudentId($student_id, $todayDay = null)
    {
        if (!$student_id) {
            return [];
        }
        $currentSessionId = (new Session())->getCurrentSessionId();
        if (!$currentSessionId) {
            return [];
        }
        $day = $todayDay ?? Carbon::now()->format('l');

        $enrollments = student_offered_courses::with(['offeredCourse:id,course_id', 'section:id'])
            ->where('student_id', $student_id)
            ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                $query->where('session_id', $currentSessionId);
            })
            ->get();
        $courseSectionMapping = $enrollments->mapWithKeys(function ($enrollment) {
            return [$enrollment->offeredCourse->course_id => $enrollment->section_id];
        });

        if ($courseSectionMapping->isEmpty()) {
            return [];
        }
        $timetable = Timetable::with([
            'course:id,name,description,lab', // Include the 'lab' attribute
            'teacher:id,name',
            'venue:id,venue',
            'dayslot:id,day,start_time,end_time',
            'juniorLecturer:id,name',
            'section'
        ])
            ->where('session_id', $currentSessionId)
            ->whereHas('dayslot', function ($query) use ($day) {
                $query->where('day', $day);
            })
            ->get()
            ->filter(function ($item) use ($courseSectionMapping) {
                return isset($courseSectionMapping[$item->course_id]) &&
                    $courseSectionMapping[$item->course_id] === $item->section_id;
            })
            ->map(function ($item) {
                
                return [
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'juniorlecturer' => $item->course->lab ? ($item->juniorLecturer->name ?? 'N/A') :'N/A',
                    'venue' => $item->venue->venue,
                    'day' => $item->dayslot->day,
                    'start_time' => $item->dayslot->start_time ? $item->dayslot->start_time : null,
                    'end_time' => $item->dayslot->end_time ? $item->dayslot->end_time : null,
                ];
            })->sortBy(function ($item) {
                // Convert start_time to a sortable format
                $time = Carbon::createFromFormat('H:i:s', $item['start_time']);
                return $time->hour < 7 ? $time->hour + 12 : $time->hour;
            })
            ->values();
        return $timetable;
    }
    public static function getFullTimetableOfEnrollmentsByStudentId($student_id)
    {
        if (!$student_id) {
            return [];
        }

        $currentSessionId = (new Session())->getCurrentSessionId();
        if (!$currentSessionId) {
            return [];
        }

        // Fetch the enrolled courses for the student in the current session
        $enrollments = student_offered_courses::with(['offeredCourse:id,course_id', 'section:id'])
            ->where('student_id', $student_id)
            ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                $query->where('session_id', $currentSessionId);
            })
            ->get();

        // Map the courses to their respective sections
        $courseSectionMapping = $enrollments->mapWithKeys(function ($enrollment) {
            return [$enrollment->offeredCourse->course_id => $enrollment->section_id];
        });

        if ($courseSectionMapping->isEmpty()) {
            return [];
        }

        // Fetch and group the timetable entries
        $timetable = Timetable::with([
            'course:id,name,description,lab', // Include 'lab' attribute
            'teacher:id,name',
            'venue:id,venue',
            'dayslot:id,day,start_time,end_time',
            'juniorLecturer:id,name',
            'section'
        ])
            ->where('session_id', $currentSessionId)
            ->get()
            ->filter(function ($item) use ($courseSectionMapping) {
                // Ensure the timetable entry matches both the course and section
                return isset($courseSectionMapping[$item->course_id]) &&
                    $courseSectionMapping[$item->course_id] === $item->section_id;
            })
            ->map(function ($item) {
               
                        return [
                            'day' => $item->dayslot->day,
                            'coursename' => $item->course->name,
                            'description' => $item->course->description,
                            'teachername' => $item->teacher->name ?? 'N/A',
                            'juniorlecturername' => $item->course->lab ? ($item->juniorLecturer->name ?? 'N/A') : null,
                            'venue' => $item->venue->venue,
                            'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                            'start_time' => $item->dayslot->start_time ? $item->dayslot->start_time: null,
                            'end_time' => $item->dayslot->end_time ?$item->dayslot->end_time: null,
                        ];
            })
            ->values();
            $groupedByDay = $timetable->groupBy('day')->map(function ($items, $day) {
                return [
                    'day' => $day,
                    'schedule' => $items->toArray()
                ];
            });
    
            return $groupedByDay->values()->toArray();
    }

}
