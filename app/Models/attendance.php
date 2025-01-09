<?php

namespace App\Models;
use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Models\teacher_offered_courses;
uSE Carbon\Carbon;
class attendance extends Model
{
    protected $table = 'attendance';
    public $timestamps = false;
    protected $primaryKey = 'id';
    protected $fillable = [
        'status',
        'date_time',
        'isLab',
        'student_id',
        'teacher_offered_course_id',
        'venue_id',
    ];
    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id', 'id');
    }
    public function teacherOfferedCourse()
    {
        return $this->belongsTo(teacher_offered_courses::class, 'teacher_offered_course_id', 'id');
    }
    public function venue()
    {
        return $this->belongsTo(Venue::class, 'venue_id');
    }
    public static function getAttendanceBySubject($teacher_offered_course_id = null, $student_id = null)
    {
        if (!$teacher_offered_course_id || !$student_id) {
            return [];
        }
        $attendanceRecords = attendance::where('teacher_offered_course_id', $teacher_offered_course_id)
            ->where('student_id', $student_id)
            ->with(['venue'])
            ->orderBy('date_time')
            ->get();
        $groupedAttendance = [
            'Class' => [
                'total_classes' => 0,
                'total_present' => 0,
                'total_absent' => 0,
                'percentage' => 0,
                'records' => [],
            ],
            'Lab' => [
                'total_classes' => 0,
                'total_present' => 0,
                'total_absent' => 0,
                'percentage' => 0,
                'records' => [],
            ]
        ];
        $totalClasses = 0;
        $totalPresent = 0;
        $totalAbsent = 0;

        foreach ($attendanceRecords as $attendance) {
            $status = ($attendance->status == 'p') ? 'Present' : 'Absent';
            $dateTime = Carbon::parse($attendance->date_time); 
            $date = $dateTime->format('Y-m-d');
            $time = $dateTime->format('H:i:s');
            $groupKey = ($attendance->isLab == 0) ? 'Class' : 'Lab';
            $groupedAttendance[$groupKey]['total_classes']++;
            if ($attendance->status == 'p') {
                $groupedAttendance[$groupKey]['total_present']++;
            } else {
                $groupedAttendance[$groupKey]['total_absent']++;
            }
            $groupedAttendance[$groupKey]['records'][] = [
                'status' => $status,
                'date' => $date,
                'time' => $time,
                'venue' => $attendance->venue->venue,
            ];
            $totalClasses++;
            if ($attendance->status == 'p') {
                $totalPresent++;
            } else {
                $totalAbsent++;
            }
        }
        foreach (['Class', 'Lab'] as $group) {
            if ($groupedAttendance[$group]['total_classes'] > 0) {
                $groupedAttendance[$group]['percentage'] = ($groupedAttendance[$group]['total_present'] / $groupedAttendance[$group]['total_classes']) * 100;
            } else {
                $groupedAttendance[$group]['percentage'] = 0;
            }
        }

        $combinedPercentage = ($totalClasses > 0) ? ($totalPresent / $totalClasses) * 100 : 0;
        $result = [
            'Total' => [
                'total_classes' => $totalClasses,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'percentage' => $combinedPercentage
            ],
            'Class' => $groupedAttendance['Class'],
            'Lab' => $groupedAttendance['Lab'],
        ];

        return $result;
    }
    public function getAttendanceByID($studentId = null)
    {
        if (!$studentId) {
            return [];
        }

        $attendanceData = [];
        try {
            $currentSessionId = (new Session())->getCurrentSessionId();
            $enrollments = student_offered_courses::where('student_id', $studentId)
                ->with('offeredCourse')
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', (new Session())->getCurrentSessionId());
                })
                ->get();
            foreach ($enrollments as $enrollment) {
                $offeredCourse = $enrollment->offeredCourse;
                $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offeredCourse->id)->first();

                if ($teacherOfferedCourse) {
                    $attendanceRecords = attendance::where('student_id', $studentId)
                        ->where('teacher_offered_course_id', $teacherOfferedCourse->id)
                        ->get();
                    $totalPresent = $attendanceRecords->where('status', 'p')->count();
                    $totalAbsent = $attendanceRecords->where('status', 'a')->count();
                    $total_Classes = $totalPresent + $totalAbsent;
                    $percentage = $total_Classes > 0 ? ($totalPresent / $total_Classes) * 100 : 100;
                    $attendanceData[] = [
                        'course_name' => $offeredCourse->course->name,
                        'teacher_offered_course_id' => $teacherOfferedCourse->id,
                        'teacher_name' => $teacherOfferedCourse->teacher->name ?? 'N/A',
                        'total_present' => $totalPresent ?? '0',
                        'total_absent' => $totalAbsent ?? '0',
                        'Percentage' => $percentage
                    ];
                }
            }
        } catch (Exception $ex) {
            return $attendanceData;
        }
        return $attendanceData;
    }
}
