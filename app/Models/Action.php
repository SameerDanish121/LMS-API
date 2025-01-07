<?php

namespace App\Models;

use Exception;
use GuzzleHttp\Psr7\Message;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
class Action extends Model
{


    // 'RegNo', 'name', 'cgpa', 'gender', 'date_of_birth', 
    // 'guardian', 'image', 'user_id', 'section_id', 'program_id', 
    // 'session_id', 'status'
    public static function AddorUpdateNewStudent($RegNo, $name, $cgpa, $gender, $dateofBirth, $guradain, $image, $user_id, $section, $program, $session, $status)
    {
        student::updateOrCreate(
            [ 'RegNo'=>$RegNo,    
            'Name'=>$name,
            'cgpa'=>$cgpa,
            'gender'=>$guradain,
            'date_of_birth'=>$dateofBirth,
            'guardian'=>$guradain,
            'image'=>$image,
            'user_id'=>$user_id,
            'section_id'=>$section,
            'program_id'=>$program,
            'status'=>$status,
            'session_id'=>$session],
            [
            'RegNo'=>$RegNo,    
            'Name'=>$name,
            'cgpa'=>$cgpa,
            'gender'=>$guradain,
            'date_of_birth'=>$dateofBirth,
            'guardian'=>$guradain,
            'image'=>$image,
            'user_id'=>$user_id,
            'section_id'=>$section,
            'program_id'=>$program,
            'status'=>$status,
            'session_id'=>$session,
        ]);

    }
    /**
     * Helper function to get file extension based on file type
     */
    private static function getFileExtension($fileType)
    {
        $mimeTypeMap = [
            'pdf' => 'pdf',
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'doc' => 'doc',
            'docx' => 'docx',
            'txt' => 'txt',
            'xlsx' => 'xlsx',
            'pptx' => 'pptx',
            'mp4' => 'mp4',
            // Add more types here as needed
        ];

        return isset($mimeTypeMap[strtolower($fileType)]) ? $mimeTypeMap[strtolower($fileType)] : null;
    }

    public static function storeFile($file, $directory, $madeUpName)
    {
        // Ensure the directory path starts with "storage/"
        try{

        }catch(Exception $ex){

        }
        $directory = 'storage/' . trim($directory, '/');

        // Create the full path for the directory
        $storagePath = storage_path('app/public/' . $directory);

        // Create the directory if it doesn't exist
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }

        // Get the file extension
        $extension = $file->getClientOriginalExtension();

        // Generate the final file name
        $fileName = $madeUpName . '.' . $extension;

        // Move the file to the specified directory
        $file->move($storagePath, $fileName);

        // Return the full path to the stored file
        return $directory . '/' . $fileName;
    }

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

        $normalizedData = preg_replace('/\s+/', ' ', $RawDATA);
        $parts = explode(' ', $normalizedData);
        $course = ltrim($parts[0]);
        $CourseData = explode('_', string: $course);
        if (count($CourseData) > 1) {
            $course = $CourseData[1];
        } else {
            throw new Exception("The string does not contain an underscore.\n");
        }
        $section = preg_replace('/^[\p{Z}\s]+/u', '', $parts[1]);
        if (preg_match('/\(([^)]+)\)/', $section, $matches)) {
            $section = $matches[1];
        } else {
            throw new Exception("No match found.");
        }


        $teacherInfoVenue = $parts[count($parts) - 1];
        $teachervenueparts = explode('_', $teacherInfoVenue);
        if (count($teachervenueparts) === 2) {
            $teacherRaw = $teachervenueparts[0];
            $venue = $teachervenueparts[1];

        } else {
            throw new Exception("Invalid format.\n");
        }
        if (count($parts) === 5) {
            $teacherInfo = $parts[2] . ' ' . $parts[3] . $teachervenueparts[0];
        } else if (count($parts) === 4) {
            $teacherInfo = $parts[2] . ' ' . $teachervenueparts[0];
        } else if (count($parts) == 3) {
            $teacherInfo = $teachervenueparts[0];
        }
        $teacherInfo = str_replace(['(', ')'], '', subject: $teacherInfo);
        if (strpos($teacherInfo, ',') !== false) {
            $parts = explode(',', $teacherInfo);
            $teacherName = trim($parts[0]);
            $juniorLecturerName = trim($parts[1]);
        } else {
            $teacherName = $teacherInfo;
            $juniorLecturerName = null;

        }

        $section_id = section::addNewSection($section);

        if (!$section_id) {
            return null;
        }

        $course = Course::where('description', $course)->first();
        if (!$course) {
            return null;
        }
        $course_id = $course->id;
        $venue_id = self::GetVenueIDByName($venue);
        $teacher_id = null;
        $juniorLecture_id = null;

        if (self::containsLab($venue)) {
            if ($teacherName && $juniorLecturerName) {
                $juniorLecture_id = self::getJuniorLecIdByName($juniorLecturerName);
                $teacher_id = (new teacher())->getIDByName($teacherName);
                if (!$teacher_id && !$juniorLecture_id) {

                    return null;
                }
                $type = 'Supervised Lab';
            } else if ($teacherName && !$juniorLecturerName) {

                $teacher_id = (new teacher())->getIDByName($teacherName);
                if (!$teacher_id) {
                    $juniorLecture_id = self::getJuniorLecIdByName($teacherName);
                    $type = 'Lab';
                } else {
                    $type = 'Supervised Lab';
                }


            } else {
                return null;
            }
        } else {
            $teacher_id = (new teacher())->getIDByName($teacherName);
            $type = 'Class';
        }
        if (!$teacher_id && !$juniorLecture_id) {
            return null;
        }
        $timetable = Timetable::firstOrCreate(
            [
                'session_id' => $sessionId,
                'section_id' => $section_id,
                'dayslot_id' => $daySlot_id,
                'course_id' => $course_id,
                'teacher_id' => $teacher_id,
                'junior_lecturer_id' => $juniorLecture_id,
                'venue_id' => $venue_id,
                'type' => $type
            ]
        );
        return $timetable;
    }
}
