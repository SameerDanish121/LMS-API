<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;
use Carbon\Carbon;
class StudentManagement extends Model
{
    // 'RegNo', 'name', 'cgpa', 'gender', 'date_of_birth', 
    // 'guardian', 'image', 'user_id', 'section_id', 'program_id', 
    // 'session_id', 'status'
    public static function updateStudentPassword($student_id, $newPassword)
    {
        $student = student::find($student_id);
        if (!$student) {
            throw new Exception("Student not found");
        }
        $user_id = $student->user_id;
        if (!$user_id) {
            throw new Exception("User ID not found for the student");
        }
        $user = user::where('id', $user_id)->first();
        if (!$user) {
            throw new Exception("User not found for the given user ID");
        }
        $user->update(['password' => $newPassword]);

        return "Password updated successfully for Student : $student->name";
    }
    public static function updateStudentImage($student_id, $file)
    {
        $student = student::find($student_id);
        if (!$student) {
            throw new Exception("Student not found");
        }
        $directory = 'Images/Student';
        $storedFilePath = FileHandler::storeFile($student->RegNo, $directory, $file);
        $student->update(['image' => $storedFilePath]);
        return "Image updated successfully for Student : $student->name";
    }
    public static function AddOrUpdateNewStudent(
        $RegNo,
        $name,
        $cgpa,
        $gender,
        $dateOfBirth,
        $guardian,
        $user_id,
        $section,
        $program,
        $session,
        $status,
        $password,
        $email
    ) {
        $user = user::where('username', $RegNo)->first();
        if ($user) {
            $user->update([
                'password' => $password,
                'email' => $email,
            ]);
            $user_id = $user->id;
        } else {
            $user_id = user::create([
                'username' => $RegNo,
                'password' => $password,
                'email' => $email,
                'role_id' => role::where('type', 'Student')->value('id'),
            ])->id;
        }
        student::updateOrCreate(
            ['RegNo' => $RegNo],
            [
                'name' => $name,
                'cgpa' => $cgpa,
                'gender' => $gender,
                'date_of_birth' => $dateOfBirth,
                'guardian' => $guardian,
                'user_id' => $user_id,
                'section_id' => (new section())->getNameByID($section) ?? null,
                'program_id' => program::where('name', $program)->value('id') ?? null,
                'status' => $status,
                'session_id' => (new session())->getSessionIdByName($session) ?? null,
            ]
        );
    }
    public static function StudentInfoById($student_id)
    {
        $student = student::where('id', $student_id)->with(['program', 'user'])
            ->first();
        $student_id = $student->pluck('id');
        $studentInfo = [
            "id" => $student->id,
            "name" => $student->name,
            "RegNo" => $student->RegNo,
            "CGPA" => $student->cgpa,
            "Gender" => $student->gender,
            "Guardian" => $student->guardian,
            "username" => $student->user->username,
            "password" => $student->user->password,
            "email" => $student->user->email,
            "InTake" => (new session())->getSessionNameByID($student->session_id),
            "Program" => $student->program->name,
            "Section" => (new section())->getNameByID($student->section_id),
            "Total Enrollments" => student_offered_courses::GetCountOfTotalEnrollments($student_id),
            "Current Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?: 'N/A',
            "Image" => FileHandler::getFileByPath($student->image),
        ];
        return $studentInfo;
    }
    public static function getActiveEnrollmentCoursesName($student_id)
    {
        $currentSessionId = (new session())->getCurrentSessionId();
        if ($currentSessionId == 0) {
            throw new Exception('No Active Session Found');
        }
        $offeredCourses = offered_courses::where('session_id', $currentSessionId)->get();
        $courses = [];
        foreach ($offeredCourses as $offeredCourse) {
            $enrolledCourse =student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offeredCourse->id)
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', $currentSessionId);
                })
                ->first();
            if ($enrolledCourse) {
                $courseName = $offeredCourse->course->name;
                $courses[] = [
                    'course_name' => $courseName,
                    'offered_course_id' => $offeredCourse->id
                ];
            }
        }
        return $courses;
    }
    public static function getAllEnrollmentCoursesName($student_id)
    {
        $enrollments =student_offered_courses::where('student_id', $student_id)
            ->with(['offeredCourse.session', 'offeredCourse.course'])
            ->get();
        $courses = [];

        foreach ($enrollments as $enrollment) {
            $offeredCourse = $enrollment->offeredCourse;
            $session = $offeredCourse->session;
            if ($session) {
                $courses[] = [
                    'course_name' => $offeredCourse->course->name,
                    'offered_course_id' => $offeredCourse->id,
                    'session_start_date' => $session->start_date
                ];
            }
        }
        usort($courses, function ($a, $b) {
            $dateA = Carbon::parse($a['session_start_date']);
            $dateB = Carbon::parse($b['session_start_date']);
            if ($dateA->isToday() || $dateA->isPast()) {
                return -1;
            }
            if ($dateB->isToday() || $dateB->isPast()) {
                return 1;
            }
            return $dateB->greaterThan($dateA) ? 1 : -1;
        });
        return $courses;
    }
}
