<?php

namespace App\Models;

use Exception;
use GuzzleHttp\Psr7\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
class Action extends Model
{
    function storeFileWithReplacement($directoryPath, $fileName, $file)
    {
        if (!File::exists($directoryPath)) {
            File::makeDirectory($directoryPath, 0755, true);
        }
        $filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
        $file->move($directoryPath, $fileName);
        return $filePath;
    }
    public static function getAttendaceDetail($studentId)
    {
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
                    $percentage = ($totalPresent / $total_Classes) * 100;
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
            return $ex->getMessage();
        }
        return $attendanceData;
    }
}
