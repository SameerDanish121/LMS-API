<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use App\Models\teacher_offered_courses;
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
    public static function getSubjectAttendance($teacher_offered_course_id=null,$student_id=null){
        if (!$teacher_offered_course_id || !$student_id) {
            return [];
        }
       return attendance::where('teacher_offered_course_id', $teacher_offered_course_id)
       ->where('student_id', $student_id)
       ->select(['status', 'date_time', 'isLab'])
       ->get();
    }

    public function getAttendanceByID($studentId=null)
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
        } catch (\Exception $ex) {
           return $attendanceData;
        }
        return $attendanceData;
    }
}
