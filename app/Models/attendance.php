<?php

namespace App\Models;
use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Models\teacher_offered_courses;
use Carbon\Carbon;
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

        $distinctCount = self::where('teacher_offered_course_id', $teacher_offered_course_id)
            ->distinct('date_time')
            ->count('date_time');

        $distinctCountLab = self::where('teacher_offered_course_id', $teacher_offered_course_id)
            ->where('isLab', 1)->distinct('date_time')
            ->count('date_time');
        $distinctCountClass = self::where('teacher_offered_course_id', $teacher_offered_course_id)
            ->where('isLab', 0)->distinct('date_time')
            ->count('date_time');
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
                'id' => $attendance->id,
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

        $groupedAttendance['Lab']['total_classes'] = $distinctCountLab;
        $groupedAttendance['Class']['total_classes'] = $distinctCountClass;

        foreach (['Class', 'Lab'] as $group) {
            if ($groupedAttendance[$group]['total_classes'] > 0) {
                $groupedAttendance[$group]['percentage'] = ($groupedAttendance[$group]['total_present'] / $groupedAttendance[$group]['total_classes']) * 100;
            } else {
                $groupedAttendance[$group]['percentage'] = 0;
            }
        }

        $combinedPercentage = ($distinctCount > 0) ? ($totalPresent / $distinctCount) * 100 : 0;
        $result = [
            'Total' => [
                'total_classes' => $distinctCount,
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
                $offeredCourse = $enrollment->offered_course_id;
                $teacherOfferedCourse = teacher_offered_courses::
                with(['section','teacher'])
                ->where('offered_course_id', $offeredCourse)
                ->where('section_id',$enrollment->section_id)->first();
                    $attendanceRecords = attendance::where('student_id', $studentId)
                        ->where('teacher_offered_course_id', $teacherOfferedCourse->id)
                        ->get();
                    $totalPresent = $attendanceRecords->where('status', 'p')->count(); 
                    $totalAbsent = $attendanceRecords->where('status', 'a')->count();
                    $total_Classes = $totalPresent + $totalAbsent;
                    $total_Classes = self::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                        ->distinct('date_time')
                        ->count('date_time');
                    $percentage = $total_Classes > 0 ? ($totalPresent / $total_Classes) * 100 : 0;
                    $oc=offered_courses::with(['course'])->find($offeredCourse);
                    $attendanceData[] = [
                        'course_name' => $oc->course->name,
                        'section_name'=>(new section())->getNameByID($teacherOfferedCourse->section->id),
                        'teacher_name' => $teacherOfferedCourse->teacher->name ?? 'N/A',
                        'Total_classes_conducted'=>$total_Classes,
                        'total_present' => $totalPresent ?? '0',
                        'total_absent' => $totalAbsent ?? '0',
                        'Percentage' => $percentage,
                        'teacher_offered_course_id' => $teacherOfferedCourse->id,
                    ];
            }
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return $attendanceData;
    }
}
