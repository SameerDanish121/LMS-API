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



    public static function EligibleToPromote($student_id)
    {
        $student = student::find($student_id);
        if (!$student) {
            return false;
        }
        $section = Section::find($student->section_id)??0;
        if (!$section || !isset($section->semester)) {
            return false;
        }
        $currentSemester = $section->semester+1;
        if ($currentSemester == 1) {
            return true;
        }
        $promotionPolicies = [
            2 => 2.0,
            3 => 2.1,
            4 => 2.2,
            5 => 2.3,
            6 => 2.4,
            7 => 2.5,
            8 => 2.6
        ];
        $requiredCgpa = $promotionPolicies[$currentSemester] ?? 2.5;
        if ($student->cgpa < $requiredCgpa) {
            return self::handleFailedPromotion($student_id, $student->user_id);
        }
        $failedCourses = student_offered_courses::where('student_id', $student_id)
            ->whereIn('grade', ['D', 'F'])
            ->with(['offeredCourse.course'])
            ->get();
        if ($failedCourses->isNotEmpty()) {
            return self::handleFailedPromotion($student_id, $student->user_id, $failedCourses);
        }
        return true;
    }

    private static function handleFailedPromotion($student_id, $user_id, $failedCourses = [])
    {
        $failedSubjectsString = "";
        foreach ($failedCourses as $course) {
            $failedSubjectsString .= $course->offeredCourse->course->name . " (" . $course->grade . "), ";
        }
        $failedSubjectsString = rtrim($failedSubjectsString, ', ');
        Notification::create([
            'title' => 'Promotion Status',
            'description' => "Dropped : You are not eligible for promotion due to CGPA | failing courses: $failedSubjectsString",
            'url' => null,
            'notification_date' => now(),
            'sender' => 'DataCell',
            'reciever' => 'Student',
            'Brodcast' => 0,
            'TL_sender_id' => null,
            'Student_Section' => null,
            'TL_receiver_id' => $user_id,
        ]);

        return false;
    }

    // 'RegNo', 'name', 'cgpa', 'gender', 'date_of_birth', 
    // 'guardian', 'image', 'user_id', 'section_id', 'program_id', 
    // 'session_id', 'status'


    public static function AddorUpdateNewStudent($RegNo, $name, $cgpa, $gender, $dateofBirth, $guradain, $image, $user_id, $section, $program, $session, $status)
    {
        student::updateOrCreate(
            [
                'RegNo' => $RegNo,
                'Name' => $name,
                'cgpa' => $cgpa,
                'gender' => $guradain,
                'date_of_birth' => $dateofBirth,
                'guardian' => $guradain,
                'image' => $image,
                'user_id' => $user_id,
                'section_id' => $section,
                'program_id' => $program,
                'status' => $status,
                'session_id' => $session
            ],
            [
                'RegNo' => $RegNo,
                'Name' => $name,
                'cgpa' => $cgpa,
                'gender' => $guradain,
                'date_of_birth' => $dateofBirth,
                'guardian' => $guradain,
                'image' => $image,
                'user_id' => $user_id,
                'section_id' => $section,
                'program_id' => $program,
                'status' => $status,
                'session_id' => $session,
            ]
        );

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
        try {

        } catch (Exception $ex) {

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
            if ($currentSessionId == 0) {
                return $attendanceData;
            }
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
        if (!$originalPath) {
            return null;
        }
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

    public static function getTaskCount($toc_id, $type)
    {
        try {
            $count = task::where('teacher_offered_course_id', $toc_id)
                ->where('type', $type)
                ->count();
            return $count + 1;
        } catch (Exception $e) {
            return 0;
        }
    }
    public static function insertOrCreateTimetable($RawDATA, $daySlot_id)
    {
        $sessionId = (new session())->getCurrentSessionId();
        if (!$sessionId) {
            $sessionId = (new session())->getUpcomingSessionId();
            if (!$sessionId) {
                return ["status" => "error", "issue" => "no session exsist"];
            }
        }
        $RawDATA = trim($RawDATA);
        if (!preg_replace('/\s+/', ' ', $RawDATA)) {
            return ["status" => "error", "issue" => "Format of {$RawDATA} is not Correct"];
        }
        $normalizedData = preg_replace('/\s+/', ' ', $RawDATA);
        $parts = explode(' ', $normalizedData);
        $course = ltrim($parts[0]);
        $CourseData = explode('_', string: $course);
        if (count($CourseData) > 1) {
            $course = $CourseData[1];
        } else {
            return ["status" => "error", "issue" => "Format of {$RawDATA} is not Correct"];
        }
        $section = preg_replace('/^[\p{Z}\s]+/u', '', ltrim($parts[1]));
        if (preg_match('/\(([^)]+)\)/', $section, $matches)) {
            $section = $matches[1];
        } else {

            return ["status" => "error", "issue" => "Format of {$RawDATA} is not Correct"];
        }


        $teacherInfoVenue = $parts[count($parts) - 1];
        $teachervenueparts = explode('_', $teacherInfoVenue);
        if (count($teachervenueparts) === 2) {
            $teacherRaw = $teachervenueparts[0];
            $venue = $teachervenueparts[1];

        } else {
            return ["status" => "error", "issue" => "Format of {$RawDATA} is not Correct"];
        }
        if (count($parts) === 5) {
            $teacherInfo = ltrim($parts[2]) . ' ' . ltrim($parts[3]) . $teachervenueparts[0];
        } else if (count($parts) === 4) {
            $teacherInfo = ltrim($parts[2]) . ' ' . ltrim($teachervenueparts[0]);
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

        $course = Course::where('description', $course)->first();
        if (!$course) {
            return ["status" => "error", "issue" => "no course exsist for this {$course} / {$RawDATA}"];
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

                    return ["status" => "error", "issue" => "teacher and junior both missing / {$RawDATA}"];
                }
                $type = 'Supervised Lab';
            } else if ($teacherName && !$juniorLecturerName) {
                $teacher_id = (new teacher())->getIDByName($teacherName);
                if (!$teacher_id) {
                    $juniorLecture_id = self::getJuniorLecIdByName($teacherName);
                    if (!$juniorLecture_id) {
                        return ["status" => "error", "issue" => "junior Lecturer Record Not Found {$teacherInfo}  {$RawDATA}"];
                    }
                    $type = 'Lab';
                } else {
                    $type = 'Supervised Lab';
                }
            } else {
                return ["status" => "error", "issue" => "Format Issue / {$RawDATA}"];
            }
        } else {
            $teacher_id = (new teacher())->getIDByName($teacherName);
            if (!$teacher_id) {
                return ["status" => "error", "issue" => "Teacher with name {$teacherName} not Found in RECORD | {$RawDATA}"];
            }
            $type = 'Class';
        }
        if (!$teacher_id && !$juniorLecture_id) {
            return ["status" => "error", "issue" => "No instructor found for /|/// {$RawDATA}"];
        }
        $timetable = [];
        $section = explode(',', $section);

        foreach ($section as $s) {
            $section_id = section::addNewSection($s);

            if (!$section_id) {

                return ["status" => "error", "issue" => "no section is created for this {$s} / {$RawDATA}"];
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
        }
        return ["status" => "success", "timetable" => $timetable];
    }
    public static function generateUniquePassword($name)
    {
        $cleanedName = strtolower(str_replace(' ', '', $name));
        $numericSuffix = rand(1000, 9999);
        $generatedPassword = $cleanedName . $numericSuffix;
        while (user::where('password', $generatedPassword)->exists()) {
            $numericSuffix = rand(1000, 9999);
            $generatedPassword = $cleanedName . $numericSuffix;
        }
        return $generatedPassword;
    }
    public static function addOrUpdateUser($username, $password, $email, $roleType)
    {
        $role = Role::where('type', $roleType)->first();
        if (!$role) {
            $role = role::create([
                "type" => $roleType
            ]);
        }
        $roleId = $role->id;
        $user = user::where('username', $username)->first();
        if ($user) {
            if (!empty($email)) {
                $user->email = $email;
            }
            $user->save();
        } else {
            $user = new user();
            $user->username = $username;
            $user->password = $password;
            $user->role_id = $roleId;
            if (!empty($email)) {
                $user->email = $email;
            }
            $user->save();
        }
        return $user->id;
    }
    public static function getMCQS($coursecontent_id)
    {
        if (!$coursecontent_id) {
            return null;
        }
        $Question = quiz_questions::where('coursecontent_id', $coursecontent_id)->with(['Options'])->get();
        if (!$Question) {
            return null;
        }
        $Question_details = $Question->map(
            function ($Question) {
                return [
                    "ID" => $Question->id,
                    'Points'=>$Question->points,
                    "Question NO" => $Question->question_no,
                    "Question" => $Question->question_text,
                    "Option 1" => $Question->Options[0]->option_text ?? null,
                    "Option 2" => $Question->Options[1]->option_text ?? null,
                    "Option 3" => $Question->Options[2]->option_text ?? null,
                    "Option 4" => $Question->Options[3]->option_text ?? null,
                    "Answer" => $Question->Options->firstWhere('is_correct', true)->option_text ?? null,
                ];
            }
        );
        return $Question_details;
    }

}
