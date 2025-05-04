<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Exception;
use Carbon\Carbon;
use function PHPUnit\Framework\isEmpty;
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
                if (!$student->image) {
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
            return [];
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
                $teacher_offered_course = teacher_offered_courses::with(['offeredCourse.course'])->where('offered_course_id', $offeredCourse->id)->where('section_id', $enrolledCourse->section_id)->first();
                if ($teacher_offered_course) {
                    $teacher_offered_course_id = $teacher_offered_course->id ?? null;
                } else {
                    $teacher_offered_course_id = null;
                }
                $courseName = $offeredCourse->course->name;
                $courses[] = [
                    'course_name' => $courseName,
                    'Session' => (new session())->getSessionNameByID($currentSessionId),
                    'Section' => (new section())->getNameByID($enrolledCourse->section_id),
                    'offered_course_id' => $offeredCourse->id,
                    'section_id' => $enrolledCourse->section_id,
                    'teacher_offered_course_id' => $teacher_offered_course_id,
                    'isLab'=>$teacher_offered_course->offeredCourse->course->lab==1,
                   
                ];
            }
        }

        return $courses;
    }
    public static function getAllEnrollmentCoursesName($student_id)
    {
        $enrollments = student_offered_courses::where('student_id', $student_id)
            ->with(['offeredCourse.session', 'offeredCourse.course', 'section'])
            ->get();
        $courses = [];
        foreach ($enrollments as $enrollment) {
            $offeredCourse = $enrollment->offeredCourse;
            $session = $offeredCourse->session;
            $section = $enrollment->section->id;
            $teacher_offered_course = teacher_offered_courses::
                where('section_id', $section)
                ->where('offered_course_id', $offeredCourse->id)->first();
            if ($session) {
                $courses[] = [
                    'course_name' => $offeredCourse->course->name,
                    'Session' => (new session())->getSessionNameByID($session->id),
                    'Section' => (new section())->getNameByID($section),
                    'offered_course_id' => $offeredCourse->id,
                    'section_id' => $section,
                    'session_start_date' => $session->start_date,
                    'session_id'=>$session->id,
                    'teacher_offered_course_id' => ($teacher_offered_course) ? $teacher_offered_course->id : null
                ];
            }
        }
        $courses = collect($courses)
            ->filter(function ($course) {
                return $course['session_start_date'] ?? '0000-00-00';
            })
            ->sortByDesc('session_start_date') // sort by session start date (desc)
            ->values();
        return $courses;
    }
    public static function getAllTask($student_id)
    {
        $enrolledCourses = self::getActiveEnrollmentCoursesName($student_id);
        $tasksGrouped = [
            'Active_Tasks' => [],
            'Upcoming_Tasks' => [],
            'Completed_Tasks' => [],
        ];
        foreach ($enrolledCourses as $enrollment) {
            $sectionId = $enrollment['section_id'];
            $offeredCourseId = $enrollment['offered_course_id'];
            $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionId)
                ->where('offered_course_id', $offeredCourseId)
                ->first();
            if ($teacherOfferedCourse) {
                $teacherOfferedCourseId = $teacherOfferedCourse->id;
                $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
                foreach ($tasks as $task) {
                    $currentDate = now();
                    $taskDetails = [
                        'task_id' => $task->id,
                        'course_name' => $enrollment['course_name'],
                        'title' => $task->title,
                        'type' => ($task->courseContent->content == 'MCQS') ? 'MCQS' : $task->type,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                    ];
                    if ($task->CreatedBy === 'Teacher') {
                        $teacher = teacher::where('id', $teacherOfferedCourse->teacher_id)->first();
                        $taskDetails['creator_type'] = 'Teacher';
                        $taskDetails['creator_name'] = $teacher->name ?? 'Unknown';
                    } else if ($task->CreatedBy === 'Junior Lecturer') {
                        $teacher_junior = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();
                        if ($teacher_junior) {
                            $juniorLecturer = juniorlecturer::where('id', $teacher_junior->juniorlecturer_id)->first();
                            $taskDetails['creator_type'] = 'Junior Lecturer';
                            $taskDetails['creator_name'] = $juniorLecturer->name ?? 'Unknown';
                        }

                    }
                    $type = ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File';
                    $taskDetails[$type] = $task->courseContent->content == 'MCQS' ? Action::getMCQS($task->courseContent->id) : ($task->courseContent->content ? asset($task->courseContent->content) : null);
                    if ($task->start_date > $currentDate) {
                        $tasksGrouped['Upcoming_Tasks'][] = $taskDetails;
                    } else if ($task->start_date <= $currentDate && $task->due_date >= $currentDate) {
                        $studentTaskResult = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $student_id)
                            ->first();
                        if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            $taskDetails['IsAttempted'] = 'Yes';
                            $taskDetails['Submission_Date_Time'] = now();
                            $taskDetails['Your_Submission'] = 'sameer danish';
                        } else {

                            $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                            if ($studentSubmission) {
                                $taskDetails['Submission_Date_Time'] = $studentSubmission->DateTime ?? 'N/A';
                                $taskDetails['Your_Submission'] = $studentSubmission->Answer ? asset($studentSubmission->Answer) : null;
                                $taskDetails['IsAttempted'] = 'Yes';
                            } else {
                                $taskDetails['IsAttempted'] = 'No';
                            }
                        }
                        $tasksGrouped['Active_Tasks'][] = $taskDetails;
                    } else {
                        $studentTaskResult = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $student_id)
                            ->first();
                        if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            $taskDetails['IsAttempted'] = 'Yes';
                            $taskDetails['IsMarked'] = 'Yes';
                        } else if ($studentTaskResult) {
                            $taskDetails['IsMarked'] = 'Yes';
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                            if ($studentSubmission) {
                                $taskDetails['Submission_Date_Time'] = $studentSubmission->DateTime ?? 'N/A';
                                $taskDetails['Your_Submission'] = $studentSubmission->Answer ? asset($studentSubmission->Answer) : null;
                                $taskDetails['IsAttempted'] = 'Yes';
                            } else {
                                $taskDetails['Submission_Date_Time'] = 'N/A';
                                $taskDetails['Your_Submission'] = 'N/A';
                                $taskDetails['IsAttempted'] = 'No';
                            }
                        } else {
                            $taskDetails['IsMarked'] = 'No';
                            $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                            if ($studentSubmission) {
                                $taskDetails['Submission_Date_Time'] = $studentSubmission->DateTime ?? 'N/A';
                                $taskDetails['Your_Submission'] = $studentSubmission->Answer ? asset($studentSubmission->Answer) : null;
                                $taskDetails['IsAttempted'] = 'Yes';
                            } else {
                                $taskDetails['Submission_Date_Time'] = 'N/A';
                                $taskDetails['Your_Submission'] = 'N/A';
                                $taskDetails['IsAttempted'] = 'No';
                            }
                        }
                        $tasksGrouped['Completed_Tasks'][] = $taskDetails;
                    }
                }
            }
        }

        return $tasksGrouped;
    }
    public static function getAllTasks($student_id)
    {

        $enrolledCourses = self::getActiveEnrollmentCoursesName($student_id);
        $tasksGrouped = [
            'Active Tasks' => [],
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
                $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
                foreach ($tasks as $task) {
                    $currentDate = now();
                    $taskDetails = [
                        'task_id' => $task->id,
                        'title' => $task->title,
                        'type' => $task->type,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
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

                        if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            $type = 'MCQS';
                            $taskDetails[$type] = Action::getMCQS($task->courseContent->id);
                            $taskDetails['Is Attempted'] = 'Yes';
                            $taskDetails['Is Marked'] = 'Yes';
                        } else if ($studentTaskResult) {
                            $type = 'File';
                            $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                            $taskDetails[$type] = asset($task->courseContent->content);
                            $taskDetails['Submission Date Time'] = $studentSubmission->DateTime ?? 'N/A';
                            $taskDetails['Your Submission'] = $studentSubmission->Answer ? asset($studentSubmission->Answer) : null;
                            $task['Is Attempted'] = 'Yes';
                            $taskDetails['Is Marked'] = 'Yes';
                        }
                    }

                    $offeredCourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->first();
                    $course = $offeredCourse ? Course::where('id', $offeredCourse->course_id)->first() : null;
                    $taskDetails['course_name'] = $course->name ?? 'Unknown';
                    if ($task->start_date > $currentDate) {
                        $remainingTime = $currentDate->diff($task->start_date);
                        $taskDetails['Time Remaining in Task to Start'] = ($remainingTime->d ?? '0') . 'D ' . ($remainingTime->h ?? '0') . 'H ' . ($remainingTime->i ?? '0') . 'M';
                        $tasksGrouped['Upcoming Tasks'][] = $taskDetails;
                    } else if ($task->due_date < $currentDate) {
                        $type = ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File';
                        if (!$task->isMarked) {
                            $studentTaskResult = student_task_result::where('Task_id', $task->id)
                                ->where('Student_id', $student_id)
                                ->first();
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                                $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;


                                $taskDetails['Is Attempted'] = 'Yes';
                                $taskDetails['Is Marked'] = 'Yes';
                            } else if ($studentTaskResult) {
                                $type = 'File';
                                $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                                $taskDetails[$type] = asset($task->courseContent->content) ?? null;
                                $taskDetails['Submission Date Time'] = $studentSubmission->DateTime ?? 'N/A';
                                $taskDetails['Your Submission'] = asset($studentSubmission->Answer) ?? null;
                                $task['Is Attempted'] = 'Yes';
                                $taskDetails['Is Marked'] = 'No';
                            }

                        }
                        $taskDetails[$type] = ($task->courseContent->content == 'MCQS')
                            ? Action::getMCQS($task->courseContent->id)
                            : asset($task->courseContent->content) ?? null;
                        $tasksGrouped['Completed Tasks'][] = $taskDetails;
                    } else {
                        $studentTaskResult = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $student_id)
                            ->first();
                        if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                            $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                            $type = 'MCQS';
                            $taskDetails[$type] = Action::getMCQS($task->courseContent->id);
                            $task['Is Attempted'] = 'Yes';
                        } else if ($studentTaskResult) {
                            $type = 'File';
                            $studentSubmission = student_task_submission::where('task_id', $task->id)->where('student_id', $student_id)->first();
                            $taskDetails[$type] = asset($task->courseContent->content) ?? null;
                            $taskDetails['Submission Date Time'] = $studentSubmission->DateTime ?? 'N/A';
                            $taskDetails['Your Submission'] = assert($studentSubmission->Answer) ?? null;
                            $task['Is Attempted'] = 'Yes';
                        }
                        $remainingTime = $currentDate->diff($task->start_date);
                        $taskDetails['Time Remaining in Task to End'] = ($remainingTime->d ?? '0') . 'D ' . ($remainingTime->h ?? '0') . 'H ' . ($remainingTime->i ?? '0') . 'M';
                        $tasksGrouped['Active Tasks'][] = $taskDetails;
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

                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
                'tasks' => [],
            ],
            'Assignment' => [
                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
                'tasks' => [],
            ],
            'LabTask' => [

                'total_marks' => 0,
                'obtained_marks' => 0,
                'percentage' => 0,
                'tasks' => [],
            ],
        ];
        $teacherOfferedCourses = teacher_offered_courses::where('offered_course_id', $offered_course_id)->get();
        foreach ($teacherOfferedCourses as $teacherOfferedCourse) {
            $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                ->where('isMarked', 1)
                ->get();
            foreach ($tasks as $task) {
                $studentTaskResult = student_task_result::where('Task_id', $task->id)
                    ->where('Student_id', operator: $student_id)
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
                    $taskDetails['Given By'] = 'Teacher';
                    $taskDetails['creator_name'] = $teacher->name ?? 'Unknown';
                } else if ($task->CreatedBy === 'Junior Lecturer') {
                    $juniorLecturer = juniorlecturer::where('id', $teacherOfferedCourse->teacher_id)->first();
                    $taskDetails['Given By'] = 'Junior Lecturer';
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
            $week = $courseContent->week;
            if (!isset($result[$week])) {
                $result[$week] = [];
            }

            if ($courseContent->type === 'Notes') {
                $courseContentTopics = coursecontent_topic::where('coursecontent_id', $courseContent->id)->get();
                $topics = [];

                foreach ($courseContentTopics as $courseContentTopic) {
                    $topic = topic::find($courseContentTopic->topic_id);

                    if ($topic) {
                        $status = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)
                            ->where('topic_id', $topic->id)
                            ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                            ->first();

                        $stat = 'Not-Covered';
                        if ($status) {
                            $stat = $status->Status == 1 ? 'Covered' : 'Not-Covered';
                        }

                        $topics[] = [
                            'topic_id' => $topic->id,
                            'topic_name' => $topic->title,
                            'status' => $stat,
                        ];
                    }
                }

                $result[$week][] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    'File' => asset($courseContent->content) ?? null,
                    'topics' => $topics,
                ];
            } else {
                $result[$week][] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    $courseContent->type == 'MCQS' ? 'MCQS' : 'File' => $courseContent->type == 'MCQS'
                        ? Action::getMCQS($courseContent->id)
                        : asset($courseContent->content),
                ];
            }
        }

        ksort($result);
        return $result;
    }
    public static function getCourseContentForSpecificWeekWithTopicsAndStatus($section_id, $offered_course_id, $week)
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
        $courseContents = coursecontent::where('offered_course_id', $offered_course_id)
            ->where('week', $week) // Filter by week
            ->get();

        $result = [];

        foreach ($courseContents as $courseContent) {
            if ($courseContent->type === 'Notes') {
                $courseContentTopics = coursecontent_topic::where('coursecontent_id', $courseContent->id)->get();
                $topics = [];

                foreach ($courseContentTopics as $courseContentTopic) {
                    $topic = topic::find($courseContentTopic->topic_id);

                    if ($topic) {
                        $status = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)
                            ->where('topic_id', $topic->id)
                            ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                            ->first();

                        $stat = 'Not-Covered';
                        if ($status) {
                            $stat = $status->Status == 1 ? 'Covered' : 'Not-Covered';
                        }

                        $topics[] = [
                            'topic_id' => $topic->id,
                            'topic_name' => $topic->title,
                            'status' => $stat,
                        ];
                    }
                }

                $result[] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    'File' => asset($courseContent->content) ?? null,
                    'topics' => $topics,
                ];
            } else {
                $result[] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    $courseContent->type == 'MCQS' ? 'MCQS' : 'File' => $courseContent->type == 'MCQS'
                        ? Action::getMCQS($courseContent->id)
                        : asset($courseContent->content),
                ];
            }
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

                }
                $course = Course::where('id', $offeredCourse->course_id)->first();
                $courseDetails = [
                    'course_name' => $course->name,
                    'course_code' => $course->code,
                    'credit_hours' => $course->credit_hours,
                    'Type' => $course->type,
                    'Is' => $course->lab == 1 ? 'Lab' : 'Theory',
                    'Short Form' => $course->description,
                    'Pre-Requisite/Main' => $course->pre_req_main == null
                        ? 'Main' : Course::where('id', $course->pre_req_main)->value('name'),
                    'section' => (new section())->getNameByID($enrolledCourse->section_id),
                    'program' => $course->program
                        ? program::where('id', $course->program_id)->value('name')
                        : 'General',
                    'teacher_name' => ($teacher && $teacher->name) ? $teacher->name : 'N/A',  // Check if teacher exists and has a name  // Check if junior lecturer exists and has a name
                    'teacher_image' => ($teacher && $teacher->image) ? asset($teacher->image) : null,
                    'offered_course_id' => $offeredCourse->id,
                    'teacher_offered_course_id' => ($teacherOfferedCourse) ? $teacherOfferedCourse->id : null,
                ];
                if ($course->lab == 1) {
                    $juniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();
                    if ($juniorLecturer) {
                        $juniorLecturerName = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('name');
                        $jlImage = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('image');
                    }
                    $courseDetails['junior_lecturer_name'] = ($juniorLecturerName) ? $juniorLecturerName : 'N/A';
                    $courseDetails['junior_image'] = $jlImage ? asset($jlImage) : null;
                }

                $courses[] = $courseDetails;
            }
        }

        return $courses;
    }
    public static function getYourPreviousEnrollments($student_id)
    {
        $currentSessionId = (new session())->getCurrentSessionId();
        $offeredCourses = offered_courses::where('session_id', '!=', $currentSessionId)->get();
        $courses = [];
        foreach ($offeredCourses as $offeredCourse) {
            $enrolledCourse = student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offeredCourse->id)
                ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                    $query->where('session_id', '!=', $currentSessionId);
                })
                ->first();
            if ($enrolledCourse) {
                $offeredCourse = offered_courses::where('id', $enrolledCourse->offered_course_id)->with('session')->first();
                $session = $offeredCourse->session;
                $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offeredCourse->id)
                    ->where('section_id', $enrolledCourse->section_id)
                    ->first();
                $teacherName = null;
                $juniorLecturerName = null;
                if ($teacherOfferedCourse) {
                    $teacher = teacher::find($teacherOfferedCourse->teacher_id);
                    $teacherName = $teacher ? $teacher->name : null;


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
                    'Is' => $course->lab == 1 ? 'Lab' : 'Theory',
                    'Pre-Requisite/Main' => $course->pre_req_main == null
                        ? 'Main' : Course::where('id', $course->pre_req_main)->value('name'),
                    'section' => (new section())->getNameByID($enrolledCourse->section_id),
                    'session_name' => $session->name . '-' . $session->year,
                    'session_start' => $session->start_date,
                    'program' => $course->program
                        ? program::where('id', $course->program_id)->value('name')
                        : 'General',
                    'teacher_name' => $teacherName ? $teacherName : 'N/A',

                    'offered_course_id' => $offeredCourse->id,
                    'teacher_offered_course_id' => ($teacherOfferedCourse) ? $teacherOfferedCourse->id : null,
                    'teacher_image' => ($teacher && $teacher->image) ? asset($teacher->image) : null,
                    'result Info' => $result,
                    'grade' => $enrolledCourse->grade ?: 'N/A',
                ];
                if ($course->lab == 1) {
                    if ($teacherOfferedCourse) {
                        $juniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();
                        if ($juniorLecturer) {
                            $juniorLecturerName = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('name');
                            $jlImage = juniorlecturer::where('id', $juniorLecturer->juniorlecturer_id)->value('image');
                        }
                        $courseDetails['junior_image'] = $jlImage ? asset($jlImage) : null;
                    }

                }
                if ($enrolledCourse->grade == 'F' || $enrolledCourse->grade == 'D') {
                    if ($currentSessionId != 0) {
                        if (offered_courses::where('course_id', $course->id)->where('session_id', $currentSessionId)->exists()) {
                            $session = session::find($currentSessionId);
                            $type = $session->name;
                            $courseDetails['can_re_enroll'] = 'Yes';
                        }
                    }
                }
                $courses[] = $courseDetails;
            }
        }
        $groupedCourses = collect($courses)->groupBy('session_name')->toArray();
        $sortedGroupedCourses = collect($groupedCourses)->sortByDesc(function ($group, $session_name) {
            return strtotime($group[0]['session_start']);
        })->toArray();

        return $sortedGroupedCourses;
        //return $courses;
    }
}
