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
        $teacher_offered_course = teacher_offered_courses::with(['offeredCourse.course'])->find($teacher_offered_course_id);
        $attendanceRecords = self::where('teacher_offered_course_id', $teacher_offered_course_id)
            ->where('student_id', $student_id)
            ->with(['venue'])
            ->orderBy('date_time', 'desc')
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
                'date_time' => $attendance->date_time,
                'venue' => $attendance->venue->venue,
                'contested'=>contested_attendance::where('Attendance_id',$attendance->id)->where('Status','Pending')->where('isResolved',0)->exists(),
            ];
            $totalClasses++;
            if ($attendance->status == 'p') {
                $totalPresent++;
            } else {
                $totalAbsent++;
            }
        }

        $groupedAttendance['Lab']['total_classes'] = $distinctCountLab;
        $groupedAttendance['Lab']['total_absent'] = $distinctCountLab - $groupedAttendance['Lab']['total_present'];
        $groupedAttendance['Class']['total_absent'] = $distinctCountClass - $groupedAttendance['Class']['total_present'];
        $groupedAttendance['Class']['total_classes'] = $distinctCountClass;

        foreach (['Class', 'Lab'] as $group) {
            if ($groupedAttendance[$group]['total_classes'] > 0) {
                $groupedAttendance[$group]['percentage'] = round(($groupedAttendance[$group]['total_present'] / $groupedAttendance[$group]['total_classes']) * 100,1);
            } else {
                $groupedAttendance[$group]['percentage'] = 0;
            }
        }
        $combinedPercentage = ($distinctCount > 0) ? ($totalPresent / $distinctCount) * 100 : 0;
        $result = [
            'isLab'=> $teacher_offered_course->offeredCourse->course->lab==1?'Lab':'Theory',
            'Class' => $groupedAttendance['Class'],
            'Total' => [
                'total_classes' => $distinctCount,
                'total_present' => $totalPresent,
                'total_absent' => $totalAbsent,
                'percentage' => $combinedPercentage
            ],
        ];
        if($teacher_offered_course->offeredCourse->course->lab==1){
            $result['Lab'] = $groupedAttendance['Lab'];
        }
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
                    
                    $total_Classes = self::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                        ->distinct('date_time')
                        ->count('date_time');
                    $totalAbsent =$total_Classes - $totalPresent;
                    $percentage = $total_Classes > 0 ? ($totalPresent / $total_Classes) * 100 : 0;
                    $oc=offered_courses::with(['course'])->find($offeredCourse);



                    $pendingRequests = contested_attendance::with(['attendance.venue', 'attendance.teacherOfferedCourse'])
                   
                    ->whereHas('attendance', function ($query) use ($studentId) {
                        $query->where('student_id',$studentId);
                    })
                    ->where('Status', 'Pending')
                    ->where('isResolved', 0)
                    ->whereHas('attendance', function ($query) use ($teacherOfferedCourse) {
                        $query->where('teacher_offered_course_id',$teacherOfferedCourse->id);
                    })
                    ->get()
                    ->sortByDesc(fn($item) => $item->attendance->date_time)
                    ->values();
                    $formatted = $pendingRequests->map(function ($item) {
                        $attendance = $item->attendance;
                    
                        return [
                            'id'=>$item->id,
                            'date_time' => $attendance->date_time,
                            'venue' => $attendance->venue->venue ?? 'N/A',
                            'type' => $attendance->isLab == 1 ? 'Junior Lecturer' : 'Lecturer',
                        ];
                    });
                    $record=[
                        'total_requests_pending' => $formatted->count(),
                        'requests' => $formatted,
                    ];
                    $attendanceData[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourse->id,
                        'course_name' => $oc->course->name,
                        'course_code'=> $oc->course->code,
                        'course_lab'=> $oc->course->lab==1?'Lab':'Theory',
                        'Updated_at'=> self::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                        ->orderBy('date_time', 'desc')
                        ->value('date_time')?? 'N/A',
                        'section_name'=>(new section())->getNameByID($teacherOfferedCourse->section->id),
                        'teacher_name' => $teacherOfferedCourse->teacher->name ?? 'N/A',
                        'junior_lec_name'=>teacher_juniorlecturer::where('teacher_offered_course_id',$teacherOfferedCourse->id)->first('juniorlecturer_id')?juniorlecturer::find(teacher_juniorlecturer::where('teacher_offered_course_id',$teacherOfferedCourse->id)->first()->juniorlecturer_id)->name : 'N/A',
                        'Total_classes_conducted'=>$total_Classes,
                        'total_present' => $totalPresent ?? '0',
                        'total_absent' => $totalAbsent ?? '0',
                        'Percentage' =>round( $percentage,1),
                        'pending_requests_count' => $record['total_requests_pending'],
                        'pending_requests' => $record['requests'],
                    ];
            }
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
        return $attendanceData;
    }
}
