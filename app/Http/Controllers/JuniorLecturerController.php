<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\attendance;
use App\Models\Course;
use App\Models\coursecontent;
use App\Models\FileHandler;
use App\Models\JuniorLecturerHandling;
use App\Models\offered_courses;
use App\Models\section;
use App\Models\session;
use App\Models\student;
use App\Models\student_task_result;
use App\Models\task;
use App\Models\teacher_offered_courses;
use App\Models\timetable;
use Illuminate\Http\Request;
use Exception;
use Carbon\Carbon;
class JuniorLecturerController extends Controller
{
    public function FullTimetable(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $timetable = JuniorLecturerHandling::getJuniorLecturerFullTimetable($jl_id);
            return response()->json([
                'status' => 'success',
                'data' => $timetable
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function YourCourses(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $course = JuniorLecturerHandling::getJuniorLecturerCourseGroupedByActivePrevious($jl_id);
            return response()->json([
                'status' => 'success',
                'data' => $course
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function YourNotification(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $message = JuniorLecturerHandling::getNotificationsForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'data' => $message
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function sendNotification(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required',
                'user_id' => 'required|integer',
                'message' => 'required|string',
                'is_broadcast' => 'required|boolean',
                'section_name' => 'nullable|string',
                'url' => 'nullable'
            ]);
            $userId = $request->input('user_id');
            $message = $request->input('message');
            $isBroadcast = $request->input('is_broadcast');
            $sectionName = $request->input('section_name', null);
            $title = $request->input('title');
            $url = $request->input('url', null);
            $notification = JuniorLecturerHandling::sendNotification($title, $userId, $message, $isBroadcast, $sectionName, $url);
            return response()->json([
                'status' => 'success',
                'notification' => $notification ? ' Sended Sucessfully' : 'Not Sended !',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function ActiveCourseInfo(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $notification = JuniorLecturerHandling::getActiveCoursesForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'course' => $notification,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTaskInfo(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $yask = JuniorLecturerHandling::categorizeTasksForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'tasks' => $yask,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTaskSubmissionList(Request $request)
    {
        try {
            $task_id = $request->task_id;
            $yask = JuniorLecturerHandling::getStudentListForTaskMarking($task_id);
            return response()->json([
                'status' => 'success',
                'notification' => $yask,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function SubmitNumber(Request $request)
    {
        try {
            $task_id = $request->task_id;
            $student_RegNo = $request->regNo;
            $obtainedMarks = $request->obtainedMarks;
            if ($task_id) {
                task::ChangeStatusOfTask($task_id);
            }
            $result = student_task_result::storeOrUpdateResult($task_id, $student_RegNo, $obtainedMarks);
            if ($result) {
                return response()->json(
                    [
                        'message' => 'OK',
                    ],
                    200
                );
            } else {
                throw new Exception('Unexpected Error Occurs !!!!! ');
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // {
    //     "submissions": [
    //       {
    //         "task_id": 1,
    //         "regNo": "2021-ARID-4583",
    //         "obtainedMarks": 85
    //       },
    //       {
    //         "task_id": 2,
    //         "regNo": "2021-ARID-4584",
    //         "obtainedMarks": 90
    //       },
    //       {
    //         "task_id": 3,
    //         "regNo": "2021-ARID-4585",
    //         "obtainedMarks": 88
    //       }
    //     ]
    //   }
    public function SubmitNumberList(Request $request)
    {
        try {
            $submissions = $request->submissions;
            $Logs = [];
            foreach ($submissions as $submission) {
                $task_id = $submission['task_id'];
                $student_RegNo = $submission['regNo'];
                $obtainedMarks = $submission['obtainedMarks'];

                if ($task_id) {
                    task::ChangeStatusOfTask($task_id);
                }
                $result = student_task_result::storeOrUpdateResult($task_id, $student_RegNo, $obtainedMarks);
                if (!$result) {
                    $Logs[] = ["Message" => "Error in Uploading the Number of $student_RegNo", "Data" => $submission];
                } else {
                    $Logs[] = ["Message" => "successfully Uploaded the Number of $student_RegNo", "Data" => $submission];
                }
            }
            return response()->json([
                'message' => 'All submissions processed successfully!',
                'data' => $Logs
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function attendanceListofLab(Request $request)
    {
        try {
            $toc_id = $request->teacher_offered_course_id;
            $attendance = JuniorLecturerHandling::attendanceListofLab($toc_id);
            return response()->json([
                'status' => 'success',
                'attendance' => $attendance,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function attendanceListofSingleStudent(Request $request)
    {
        try {
            $toc_id = $request->toc_id;
            $student_id = $request->student_id;

            $attendance = JuniorLecturerHandling::FullattendanceListForSingleStudent($toc_id, $student_id);
            return response()->json([
                'status' => 'success',
                'attendance' => $attendance,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function storeTask(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required',
                'type' => 'required|in:Quiz,Assignment,LabTask',
                'coursecontent_id' => 'required',
                'points' => 'required|numeric|min:0',
                'start_date' => 'required|date',
                'due_date' => 'required|date|after:start_date',
                'course_name' => 'required|string|max:255',
                'sectioninfo' => 'required|string'
            ]);
            $courseName = $validatedData['course_name'];
            $sectionInfo = $validatedData['sectioninfo'];
            $points = $validatedData['points'];
            $startDate = $validatedData['start_date'];
            $dueDate = $validatedData['due_date'];
            $coursecontent_id = $validatedData['coursecontent_id'];
            $sections = explode(',', $sectionInfo);
            $course = Course::where('name', $courseName)->first();
            if (!$course) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Course '{$courseName}' not found.",
                ], 404);
            }
            $course_content=coursecontent::find($coursecontent_id);
                if(!$course_content){
                    return response()->json([
                        'status' => 'error',
                        'message' => "No Task is Found with given Cradentails ",
                    ], 404);
                }
            $type = $course_content->type;
            $insertedTasks = [];
            foreach ($sections as $sectionName) {
                $sectionName = trim($sectionName);
                $section = section::addNewSection($sectionName);
                if (!$section) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Section '{$sectionName}' not found.",
                    ], 404);
                }

                $sectionId = $section;

                $courseId = $course->id;

                $currrentSession = (new session())->getCurrentSessionId();

                $offered_course_id = offered_courses::where('session_id', $currrentSession)
                    ->where('course_id', $course->id)->value('id');

                $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionId)
                    ->where('offered_course_id', $offered_course_id)
                    ->first();

                if (!$teacherOfferedCourse) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "Teacher-offered course not found for section '{$sectionName}' and course '{$courseName}'.",
                    ], 404);
                }
                $taskNo = Action::getTaskCount($teacherOfferedCourse->id, $type);
                if ($taskNo > 0 && $taskNo < 10) {
                    $taskNo = "0" . $taskNo;
                }
                
                $filename = $course->description. $course_content->title.'-' . $type . $taskNo . '-' . $sectionName;
                $title = $filename;
                $teacherOfferedCourseId = $teacherOfferedCourse->id;
                $taskData = [
                    'title' => $title,
                    'type' => $type,
                    'CreatedBy' => 'Junior Lecturer',
                    'points' => $points,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'coursecontent_id' => $coursecontent_id,
                    'teacher_offered_course_id' => $teacherOfferedCourseId,
                    'isMarked' => false,
                ];
                $task=task::where('teacher_offered_course_id',$teacherOfferedCourse)
                ->where('coursecontent_id',$coursecontent_id)->first();
                if($task){
                    $task->update($taskData);
                    $insertedTasks[] = ['status'=>'Task is Already Allocated ! Just Updated the informations','task'=>$task];
                }else{
                    $task = Task::create($taskData);
                    $insertedTasks[] = ['status'=>'Task Allocated Successfully','task'=>$task];
                }
               
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Tasks inserted successfully.',
                'tasks' => $insertedTasks,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getLabAttendanceList(Request $request)
    {
        try {
            $teacher_offered_course_id = $request->input('teacher_offered_course_id');
            if (!$teacher_offered_course_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'teacher_offered_course_id is required.',
                ], 400);
            }
            $attendanceList = JuniorLecturerHandling::getLabAttendanceList($teacher_offered_course_id);
            return response()->json([
                'status' => 'success',
                'data' => $attendanceList,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function markSingleAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'student_id' => 'required',
            'teacher_offered_course_id' => 'required',
            'status' => 'required|in:p,a',
            'date_time' => 'required|date_format:Y-m-d H:i:s',
            'isLab' => 'required|boolean',
            'venue_id' => 'required', 
        ]);

        try {
            $teacherCourse = teacher_offered_courses::find($validatedData['teacher_offered_course_id']);
            $student = student::find($validatedData['student_id']);

            if (!$teacherCourse || !$student) {
                return response()->json(['message' => 'Invalid teacher course or student'], 404);
            }

            attendance::create([
                'status' => $validatedData['status'],
                'date_time' => $validatedData['date_time'],
                'isLab' => $validatedData['isLab'],
                'student_id' => $validatedData['student_id'],
                'teacher_offered_course_id' => $validatedData['teacher_offered_course_id'],
                'venue_id' => $validatedData['venue_id'], // Include venue_id in the insert
            ]);

            return response()->json(['message' => 'Attendance marked successfully'], 201);

        } catch (Exception $e) {
            return response()->json(['message' => 'Error marking attendance', 'error' => $e->getMessage()], 500);
        }
    }
    // {
    //     "attendance_records": [
    //       {
    //         "student_id": 1,
    //         "teacher_offered_course_id": 1,
    //         "status": "p",
    //         "date_time": "2025-01-12 09:00:00",
    //         "isLab": true,
    //         "venue_id": 2
    //       },
    //       {
    //         "student_id": 2,
    //         "teacher_offered_course_id": 1,
    //         "status": "a",
    //         "date_time": "2025-01-12 09:00:00",
    //         "isLab": false,
    //         "venue_id": 3
    //       }
    //     ]
    //   }
      
    public function markBulkAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'attendance_records' => 'required|array',
            'attendance_records.*.student_id' => 'required',
            'attendance_records.*.teacher_offered_course_id' => 'required',
            'attendance_records.*.status' => 'required|in:p,a',
            'attendance_records.*.date_time' => 'required|date_format:Y-m-d H:i:s',
            'attendance_records.*.isLab' => 'required|boolean',
            'attendance_records.*.venue_id' => 'required',
        ]);
    
        try {
            $attendanceRecords = $validatedData['attendance_records'];
    
            foreach ($attendanceRecords as $attendanceData) {
                $teacherCourse = teacher_offered_courses::find($attendanceData['teacher_offered_course_id']);
                $student = student::find($attendanceData['student_id']);
                
                if (!$teacherCourse || !$student) {
                    continue; 
                }
                attendance::create([
                    'status' => $attendanceData['status'],
                    'date_time' => $attendanceData['date_time'],
                    'isLab' => $attendanceData['isLab'],
                    'student_id' => $attendanceData['student_id'],
                    'teacher_offered_course_id' => $attendanceData['teacher_offered_course_id'],
                    'venue_id' => $attendanceData['venue_id'], 
                ]);
            }
            return response()->json(['message' => 'Attendance records marked successfully'], 201);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error marking attendance', 'error' => $e->getMessage()], 500);
        }
    }
    public function getTodayLabClassesWithTeacherCourseAndVenue(Request $request)
    {
        try {
            $juniorLecturerId = $request->juniorLecturerId;
            if (!$juniorLecturerId) {
                return response()->json([
                    'message' => 'Junior Lecturer ID is required'
                ], 400);
            }
            $timetable = JuniorLecturerHandling::getTodayLabClassesWithTeacherCourseAndVenue($juniorLecturerId);
            return response()->json([
                'message' => 'Successfully fetched today\'s lab classes',
                'data' => $timetable
            ], 200);
        } catch (Exception $e) {
            // Handle any potential errors
            return response()->json([
                'message' => 'Error fetching today\'s lab classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

   
}
