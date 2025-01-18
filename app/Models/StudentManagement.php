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
    public static function addOrUpdateStudent(
        $regNo,
        $name,
        $cgpa,
        $gender,
        $date_of_birth,
        $guardian,
        $image = null,
        $user_id,
        $section_id,
        $program_id,
        $session_id,
        $status
    ) {
        try {
            $student = student::where('RegNo', $regNo)->first();
            $data = [
                'RegNo' => $regNo,
                'name' => $name,
                'cgpa' => $cgpa,
                'gender' => $gender,
                'date_of_birth' => $date_of_birth,
                'guardian' => $guardian,
                'user_id' => $user_id,
                'section_id' => $section_id,
                'program_id' => $program_id,
                'session_id' => $session_id,
                'status' => $status,
            ];
            if ($student) {
                if(!$student->image){
                    $data['image'] = $student->image;
                }
                $student->update($data);
            } else {
                $student = student::create($data);
            }
            return $student->id;
        } catch (Exception $exception) {
           return null;
        }
    }
    
    // public static function addOrUpdateStudent(
    //     $regNo, $name, $cgpa, $gender, $date_of_birth, $guardian, 
    //     $image = null, $user_id, $section_id, $program_id, $session_id, $status
    // ) {
    //     try{
    //         $student = student::where('RegNo', $regNo)->first();
           
    //         $data = [
    //             'RegNo' => $regNo,
    //             'name' => $name,
    //             'cgpa' => $cgpa,
    //             'gender' => $gender,
    //             'date_of_birth' => $date_of_birth,
    //             'guardian' => $guardian,
    //             'image' => $image,
    //             'user_id' => $user_id,
    //             'section_id' => $section_id,
    //             'program_id' => $program_id,
    //             'session_id' => $session_id,
    //             'status' => $status,
    //         ];
    //         $student = student::updateOrCreate(
    //             ['RegNo' => $regNo], 
    //             $data             
    //         );
    //         return $student->id;
    //     }catch(Exception $exception){
    //         throw new Exception($exception->getMessage());
    //     }
    // }
    public static function getSessionIdsByStudentId($student_id)
    {
        if (!$student_id) {
            throw new Exception('Student ID is required.');
        }

        $sessionIds = student_offered_courses::where('student_id', $student_id)
            ->join('offered_courses', 'student_offered_courses.offered_course_id', '=', 'offered_courses.id')
            ->distinct()
            ->pluck('offered_courses.session_id');
        $sessionInfo = [];
        foreach ($sessionIds as $s) {
            $sessionInfo[] = ["ID" => $s, "Name" => (new session())->getSessionNameByID($s)];
        }
        return $sessionInfo;
    }

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
            $enrolledCourse = student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offeredCourse->id)
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', $currentSessionId);
                })
                ->first();
            if ($enrolledCourse) {
                $courseName = $offeredCourse->course->name;
                $courses[] = [
                    'course_name' => $courseName,
                    'offered_course_id' => $offeredCourse->id,
                    'section_id' => $enrolledCourse->section_id,
                ];
            }
        }
        return $courses;
    }
    public static function getAllEnrollmentCoursesName($student_id)
    {
        $enrollments = student_offered_courses::where('student_id', $student_id)
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
                    'section_id' => $offeredCourse->section_id,
                    'session_start_date' => $session->start_date,

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
    public static function getAllTask($student_id)
    {

        $enrolledCourses = self::getActiveEnrollmentCoursesName($student_id);
        $tasksGrouped = [
            'Pending Tasks' => [],
            'Upcoming Tasks' => [],
            'Completed Tasks' => [],
        ];
        foreach ($enrolledCourses as $enrollment) {
            $sectionId = $enrollment['section_id'];
            $offeredCourseId = $enrollment['offered_course_id'];
            $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionId)
                ->where('offered_course_id', $offeredCourseId)
                ->first();
            if ($teacherOfferedCourse) {
                $teacherOfferedCourseId = $teacherOfferedCourse->id;
                $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
                foreach ($tasks as $task) {
                    $currentDate = now();
                    $taskDetails = [
                        'task_id' => $task->id,
                        'title' => $task->title,
                        'type' => $task->type,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        $task->type => FileHandler::getFileByPath($task->path) ?? null
                    ];
                    if ($task->CreatedBy === 'Teacher') {
                        $teacher = teacher::where('id', $teacherOfferedCourse->teacher_id)->first();
                        $taskDetails['creator_type'] = 'Teacher';
                        $taskDetails['creator_name'] = $teacher->name ?? 'Unknown';
                    } else if ($task->CreatedBy === 'Junior Lecturer') {
                        $juniorLecturer = juniorlecturer::where('id', $teacherOfferedCourse->teacher_id)->first();
                        $taskDetails['creator_type'] = 'Junior Lecturer';
                        $taskDetails['creator_name'] = $juniorLecturer->name ?? 'Unknown';
                    }
                    if ($task->isMarked) {
                        $studentTaskResult = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $student_id)
                            ->first();
                        $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                    }
                    $offeredCourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->first();
                    $course = $offeredCourse ? Course::where('id', $offeredCourse->course_id)->first() : null;
                    $taskDetails['course_name'] = $course->name ?? 'Unknown';
                    if ($task->start_date > $currentDate) {
                        $remainingTime = $currentDate->diff($task->start_date);
                        $taskDetails['Time Remaining in Task to Start'] = ($remainingTime->d ?? '0') . 'D ' . ($remainingTime->h ?? '0') . 'H ' . ($remainingTime->i ?? '0') . 'M';
                        $tasksGrouped['Upcoming Tasks'][] = $taskDetails;
                    } else if ($task->due_date < $currentDate) {
                        $tasksGrouped['Completed Tasks'][] = $taskDetails;
                    } else {
                        $remainingTime = $currentDate->diff($task->start_date);
                        $taskDetails['Time Remaining in Task to End'] = ($remainingTime->d ?? '0') . 'D ' . ($remainingTime->h ?? '0') . 'H ' . ($remainingTime->i ?? '0') . 'M';
                        $tasksGrouped['Pending Tasks'][] = $taskDetails;
                    }
                }
            }
        }
        return $tasksGrouped;
    }

    public static function getSubmittedTasksGroupedByTypeWithStats($student_id, $offered_course_id)
    {
        $groupedTasks = [
            'Quiz' => [
                'tasks' => [],
                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
            ],
            'Assignment' => [
                'tasks' => [],
                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
            ],
            'LabTask' => [
                'tasks' => [],
                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
            ],
        ];
        $teacherOfferedCourses = teacher_offered_courses::where('offered_course_id', $offered_course_id)->get();
        foreach ($teacherOfferedCourses as $teacherOfferedCourse) {
            $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                ->where('isMarked', 1)
                ->get();
            foreach ($tasks as $task) {
                $studentTaskResult = student_task_result::where('Task_id', $task->id)
                    ->where('Student_id', $student_id)
                    ->first();
                $obtainedMarks = $studentTaskResult->ObtainedMarks ?? 0;
                $taskDetails = [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'type' => $task->type,
                    'points' => $task->points,
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'obtained_points' => $obtainedMarks,
                ];
                if ($task->CreatedBy === 'Teacher') {
                    $teacher = teacher::where('id', $teacherOfferedCourse->teacher_id)->first();
                    $taskDetails['creator_type'] = 'Teacher';
                    $taskDetails['creator_name'] = $teacher->name ?? 'Unknown';
                } else if ($task->CreatedBy === 'Junior Lecturer') {
                    $juniorLecturer = juniorlecturer::where('id', $teacherOfferedCourse->teacher_id)->first();
                    $taskDetails['creator_type'] = 'Junior Lecturer';
                    $taskDetails['creator_name'] = $juniorLecturer->name ?? 'Unknown';
                }
                $offeredCourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->first();
                $course = $offeredCourse ? Course::where('id', $offeredCourse->course_id)->first() : null;
                $taskDetails['course_name'] = $course->name ?? 'Unknown';
                $groupedTasks[$task->type]['tasks'][] = $taskDetails;
                $groupedTasks[$task->type]['total_marks'] += $task->points;
                $groupedTasks[$task->type]['obtained_marks'] += $obtainedMarks;
            }
        }
        foreach ($groupedTasks as $type => $group) {
            $totalMarks = $group['total_marks'];
            $obtainedMarks = $group['obtained_marks'];
            $groupedTasks[$type]['percentage'] = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
        }

        return $groupedTasks;
    }
    public static function getCourseContentWithTopicsAndStatus($section_id, $offered_course_id)
    {
        $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offered_course_id)
            ->where('section_id', $section_id)
            ->first();
        if (!$teacherOfferedCourse) {
            return [
                'error' => 'No teacher offered course found for the given section and offered course.'
            ];
        }
        $teacher_offered_course_id = $teacherOfferedCourse->id;
        $courseContents = coursecontent::where('offered_course_id', $offered_course_id)->get();
        $result = [];
        foreach ($courseContents as $courseContent) {
            $courseContentTopics = coursecontent_topic::where('coursecontent_id', $courseContent->id)->get();
            $topics = [];
            foreach ($courseContentTopics as $courseContentTopic) {
                $topic = topic::find($courseContentTopic->topic_id);

                if ($topic) {
                    $status = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)
                        ->where('topic_id', $topic->id)
                        ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                        ->first();

                    $topics[] = [
                        'topic_id' => $topic->id,
                        'topic_name' => $topic->title,
                        'status' => $status->Status == 1 ? 'Covered' : 'Not-Covered',
                    ];
                }
            }
            $result[] = [
                'course_content_id' => $courseContent->id,
                'title' => $courseContent->title,
                'type' => $courseContent->type,
                'week' => $courseContent->week,
                'File' => FileHandler::getFileByPath($courseContent->content),
                'topics' => $topics,
            ];
        }

        return $result;
    }
    public static function getCourseContentForSpecificWeekWithTopicsAndStatus($section_id, $offered_course_id, $weekNo = null)
    {
        $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offered_course_id)
            ->where('section_id', $section_id)
            ->first();

        if (!$teacherOfferedCourse) {
            return [
                'error' => 'No teacher offered course found for the given section and offered course.'
            ];
        }

        $teacher_offered_course_id = $teacherOfferedCourse->id;
        $query = coursecontent::where('offered_course_id', $offered_course_id);
        if (!is_null($weekNo)) {
            $query->where('week', $weekNo);
        }
        $courseContents = $query->get();
        $result = [];

        foreach ($courseContents as $courseContent) {
            $courseContentTopics = coursecontent_topic::where('coursecontent_id', $courseContent->id)->get();
            $topics = [];

            foreach ($courseContentTopics as $courseContentTopic) {
                $topic = topic::find($courseContentTopic->topic_id);

                if ($topic) {
                    $status = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)
                        ->where('topic_id', $topic->id)
                        ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                        ->first();

                    $topics[] = [
                        'topic_id' => $topic->id,
                        'topic_name' => $topic->title,
                        'status' => $status && $status->Status == 1 ? 'Covered' : 'Not-Covered',
                    ];
                }
            }

            $result[] = [
                'course_content_id' => $courseContent->id,
                'title' => $courseContent->title,
                'type' => $courseContent->type,
                'week' => $courseContent->week,
                'File' => FileHandler::getFileByPath($courseContent->content),
                'topics' => $topics,
            ];
        }

        return $result;
    }
    public static function createContestedAttendance($attendanceId)
    {
        $existingRecord = contested_attendance::where('Attendance_id', $attendanceId)->first();
        if (!$existingRecord) {
            $contestedAttendance = contested_attendance::create([
                'Attendance_id' => $attendanceId,
                'Status' => 'Pending',
                'isResolved' => 0
            ]);
            return $contestedAttendance;
        }
        return null;
    }
    public static function getYourEnrollments($student_id)
    {
        $currentSessionId = (new session())->getCurrentSessionId();
        if ($currentSessionId == 0) {
            throw new Exception('No Active Session Found');
        }
        $offeredCourses = offered_courses::where('session_id', $currentSessionId)->get();
        $courses = [];
        foreach ($offeredCourses as $offeredCourse) {
            $enrolledCourse = student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offeredCourse->id)
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', $currentSessionId);
                })
                ->first();
            if ($enrolledCourse) {
                $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offeredCourse->id)
                    ->where('section_id', $enrolledCourse->section_id)
                    ->first();

                $teacherName = null;
                $juniorLecturerName = null;
                if ($teacherOfferedCourse) {

                    $teacher = teacher::find($teacherOfferedCourse->teacher_id);
                    $teacherName = $teacher ? $teacher->name : null;


                    $juniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();
                    if ($juniorLecturer) {
                        $juniorLecturerName = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('name');
                    }
                }
                $course = Course::where('id', $offeredCourse->course_id)->first();
                $courseDetails = [
                    'course_name' => $course->name,
                    'course_code' => $course->code,
                    'credit_hours' => $course->credit_hours,
                    'Type' => $course->type,
                    'Short Form' => $course->description,
                    'Pre-Requisite/Main' => $course->pre_req_main == null
                        ? 'Main' : Course::where('id', $course->pre_req_main)->value('name'),
                    'section' => (new section())->getNameByID($enrolledCourse->section_id),
                    'program' => $course->program
                        ? program::where('id', $course->program_id)->value('name')
                        : 'N/A',
                    'teacher_name' => ($teacher && $teacher->name) ? $teacher->name : 'N/A',  // Check if teacher exists and has a name
                    'junior_lecturer_name' => ($juniorLecturerName) ? $juniorLecturerName : 'N/A',  // Check if junior lecturer exists and has a name
                    'offered_course_id' => $offeredCourse->id,
                ];

                $courses[] = $courseDetails;
            }
        }

        return $courses;
    }
    public static function getYourPreviousEnrollments($student_id)
    {
        $currentSessionId = (new session())->getCurrentSessionId();
        if ($currentSessionId == 0) {
            throw new Exception('No Active Session Found');
        }
        $offeredCourses = offered_courses::where('session_id', '!=', $currentSessionId)->get();
        $courses = [];

        foreach ($offeredCourses as $offeredCourse) {
            $enrolledCourse = student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offeredCourse->id)
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', '!=', $currentSessionId);  // Exclude the current session
                })
                ->first();

            if ($enrolledCourse) {
                $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offeredCourse->id)
                    ->where('section_id', $enrolledCourse->section_id)
                    ->first();

                $teacherName = null;
                $juniorLecturerName = null;
                if ($teacherOfferedCourse) {

                    $teacher = teacher::find($teacherOfferedCourse->teacher_id);
                    $teacherName = $teacher ? $teacher->name : null;

                    $juniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();
                    if ($juniorLecturer) {
                        $juniorLecturerName = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('name');
                    }
                }
                $course = Course::where('id', $offeredCourse->course_id)->first();
                $subjectResult = subjectresult::where('student_offered_course_id', $enrolledCourse->id)->first();
                if ($subjectResult) {
                    $result = $enrolledCourse->grade == 'F' ? 'Failed' : $subjectResult;
                } else {
                    $result = $enrolledCourse->grade == 'F' ? 'Failed' : 'N/A';
                }
                $courseDetails = [
                    'course_name' => $course->name,
                    'course_code' => $course->code,
                    'credit_hours' => $course->credit_hours,
                    'Type' => $course->type,
                    'Short Form' => $course->description,
                    'Pre-Requisite/Main' => $course->pre_req_main == null
                        ? 'Main' : Course::where('id', $course->pre_req_main)->value('name'),
                    'section' => (new section())->getNameByID($enrolledCourse->section_id),
                    'program' => $course->program
                        ? program::where('id', $course->program_id)->value('name')
                        : 'N/A',
                    'teacher_name' => $teacherName ? $teacherName : 'N/A',
                    'junior_lecturer_name' => ($juniorLecturerName) ? $juniorLecturerName : 'N/A',
                    'offered_course_id' => $offeredCourse->id,
                    'result Info' => $result,
                    'grade' => $enrolledCourse->grade ?: 'N/A',
                ];

                $courses[] = $courseDetails;
            }
        }

        return $courses;
    }
}
