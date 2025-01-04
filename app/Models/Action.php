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

    public static function getImageByPath($originalPath = null)
    {
        if (file_exists(public_path($originalPath))) {
            $imageContent = file_get_contents(public_path($originalPath));
            return base64_encode($imageContent);
        } else {
            return null;
        }
    }
    public static function GetVenueIDByName($venueName)
    {
        // Check if the venue exists by name
        $venue = venue::firstOrCreate(
            ['venue' => $venueName],
            ['venue' => $venueName]
        );

        // Return the ID of the found or newly created venue
        return $venue->id;
    }
    public static function getJuniorLecIdByName($name)
    {
        $juniorLecturer = juniorlecturer::where('name', $name)->first();
        return $juniorLecturer ? $juniorLecturer->id : null;
    }
    public static function containsLab($venue)
{
    // Check if 'Lab' exists in the string
    if (strpos($venue, 'Lab') !== false) {
        return true;
    }

    return false;
}
    public static function insertOrCreateTimetable($RawDATA, $daySlot_id)
    {
        $sessionId = (new session())->getCurrentSessionId();
        if (!$sessionId) {
            $sessionId = (new session())->getUpcomingSessionId();
            if (!$sessionId) {
                return null;
            }
        }
        $RawDATA = trim($RawDATA);
        if (preg_match('/^([A-Za-z0-9-]+)_(\w+)\s?\((.*?)\)_\d+\s?\((.*?)\)_([\w\s]+)$/', $RawDATA, $matches)) {
            $courseShortForm = $matches[2] ?? '';
        $section = $matches[3] ?? '';
        $teacherInfo = $matches[4] ?? '';
        $venue = $matches[5] ?? '';
        echo $venue;
        if (strpos($teacherInfo, ',') !== false) {
            $parts = explode(',', $teacherInfo);
            $teacherName = trim($parts[0]);
            $juniorLecturerName = trim($parts[1]);
        } else {
            $teacherName = $teacherInfo;
            $juniorLecturerName = null;
        }
        $section_id = section::addNewSection($section);
        $course = Course::where('description', $courseShortForm)->first();
        if (!$course) {
            return null;
        }
        $course_id = $course->id;
        $venue_id = self::GetVenueIDByName($venue);
      
        if (self::containsLab($venue)) {
            if($teacherName&&$juniorLecturerName){
                $juniorLecture_id = self::getJuniorLecIdByName($juniorLecturerName);
                $teacher_id = (new teacher())->getIDByName($teacherName);
                if (!$teacher_id) {
                    return null;
                }
                $type='Supervised Lab';
            }if($teacherName&&!$juniorLecturerName){
                $juniorLecture_id = self::getJuniorLecIdByName($teacherName);
                $type='Lab';
            }
        }else{
            $teacher_id = (new teacher())->getIDByName($teacherName);
            $type='Class';
        }
        return Timetable::firstOrCreate(
            [
                'session_id' => $sessionId,
                'section_id' => $section_id,
                'dayslot_id' => $daySlot_id,
                'course_id' => $course_id, 
                'teacher_id' => $teacher_id??null, 
                'junior_lecturer_id' => $juniorLecture_id??null, 
                'venue_id' => $venue_id   , 
                'type' => $type
            ]
        );
        }else{
            throw new Exception("Regex NOT MATCH".$RawDATA);
        }
        
        

    }
}
