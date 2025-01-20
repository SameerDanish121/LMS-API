<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\contested_attendance;
use App\Models\Course;
use App\Models\coursecontent;
use App\Models\FileHandler;
use App\Models\grader;
use App\Models\grader_task;
use App\Models\notification;
use App\Models\offered_courses;
use App\Models\section;
use App\Models\student;
use App\Models\student_task_result;
use App\Models\teacher;
use App\Models\teacher_grader;
use App\Models\teacher_offered_courses;
use App\Models\venue;
use Illuminate\Console\View\Components\Task;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;

class TeacherModuleController extends Controller
{
    public function ContestList(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $teacher = teacher::find($teacher_id);
            if (!$teacher) {
                throw new Exception('No Record FOR Given id of Teacher found !');
            }
            $contents = contested_attendance::with(['attendance.teacherOfferedCourse.offeredCourse.course'])
                ->whereHas('attendance.teacherOfferedCourse', function ($query) use ($teacher_id) {
                    $query->where('teacher_id', $teacher_id);
                })->whereHas('attendance', function ($query) use ($teacher_id) {
                    $query->where('isLab', 0);
                })->orderBy('id', 'asc')
                ->get();
            $customData = $contents->map(function ($item) {
                return [
                    'Message' => 'The Attendance with Following Info is Contested By Student !',
                    'Student Name' => student::find($item->attendance->student_id)->name ?? 'N/A',
                    'Student Reg NO' => student::find($item->attendance->student_id)->RegNo ?? 'N/A',
                    'Date & Time' => $item->attendance->date_time,
                    'Venue' => venue::find($item->attendance->venue_id)->venue ?? 'N/A',
                    'Course' => $item->attendance->teacherOfferedCourse->offeredCourse->course->name ?? null,
                    'Section' => (new section())->getNameByID($item->attendance->teacherOfferedCourse->section_id) ?? 'N/A',
                    'Status' => $item->Status,
                    'contested_id' => $item->id,
                    'attendance_id' => $item->attendance->id ?? null,
                    'teacher_offered_course' => $item->attendance->teacherOfferedCourse->id ?? null,
                ];
            });
            return response()->json([
                'success' => 'Fetched Successfully!',
                'Student Contested Attendace' => $customData
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 404);

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
                'sender_teacher_id' => 'required|exists:teachers,id',
                'receiver_student_id' => 'required|exists:students,id',
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
            ]);

            $teacher = teacher::find($request->sender_teacher_id);
            $student = student::find($request->receiver_student_id);

            if (!$teacher || !$student) {
                throw new Exception('Sender or receiver not found.');
            }
            $notification = notification::create([
                'title' => $request->title,
                'description' => $request->description,
                'url' => null, // Add URL if needed for the notification
                'notification_date' => now(),
                'sender' => $teacher->id,
                'reciever' => $student->id,
                'Brodcast' => 0, // Not a broadcast
                'TL_sender_id' => $teacher->user_id,
                'Student_Section' => $student->section_id, // Assuming student belongs to a section
                'TL_receiver_id' => $student->user_id, // Assuming student has a `user_id`
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification sent successfully.',
                'notification' => $notification,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
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
    public function ProcessContest(Request $request)
    {
        try {
            $request->validate([
                'verification' => 'required|in:Accepted,Rejected',
                'contest_id' => 'required|exists:contested_attendance,id',
            ]);
            $verification = $request->verification;
            $contest_id = $request->contest_id;
            $contested = contested_attendance::with(['attendance.teacherOfferedCourse.offeredCourse.course', 'attendance.venue', 'attendance.student', 'attendance.teacherOfferedCourse.teacher'])
                ->find($contest_id);
            if (!$contested) {
                throw new Exception('Contested Attendance record not found.');
            }
            $attendance = $contested->attendance;
            $message = "{$attendance->student->name}({$attendance->student->RegNo}) : Your contest for the attendance of course '{$attendance->teacherOfferedCourse->offeredCourse->course->name}', ";
            $message .= "held in venue '{$attendance->venue->venue}' on {$attendance->date_time}, ";
            $message .= "by teacher '{$attendance->teacherOfferedCourse->teacher->name}' has been ";
            if ($verification === 'Accepted') {
                $attendance->status = 'p';
                $attendance->save();
                $message .= 'Accepted.';
            } else {
                $message .= 'Rejected.';
            }
            $notification = notification::create([
                'title' => 'Contest Verification',
                'description' => $message,
                'url' => null,
                'notification_date' => now(),
                'sender' => 'Teacher',
                'reciever' => 'Student',
                'Brodcast' => 0,
                'TL_sender_id' => $attendance->teacherOfferedCourse->teacher->user_id,
                'TL_receiver_id' => $attendance->student->user_id,
            ]);
            $contested->delete();
            return response()->json([
                'status' => 'success',
                'message' => $message,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid contest ID',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function Sample(Request $request)
    {
        try {
            $id = $request->id;
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private static function getMarkingInfo($taskId)
    {
        $marks = student_task_result::where('Task_id', $taskId)
            ->join('student', 'student.id', '=', 'student_task_result.Student_id')
            ->select('student_task_result.ObtainedMarks as obtained_marks', 'student.name as student_name')
            ->orderBy('obtained_marks', 'desc')
            ->get();
        if ($marks->isEmpty()) {
            return null;
        }
        $topMark = $marks->first();
        $worstMark = $marks->last();
        $averageMarks = $marks->avg('obtained_marks');

        return [
            'top' => [
                'student_name' => $topMark->student_name,
                'obtained_marks' => $topMark->obtained_marks,
                'title' => 'Good',
            ],
            'average' => [
                'student_name' => $topMark->student_name,
                'obtained_marks' => round($averageMarks, 2),  // Average marks rounded to 2 decimals
                'title' => 'Average',
            ],
            'worst' => [
                'student_name' => $worstMark->student_name,
                'obtained_marks' => $worstMark->obtained_marks,
                'title' => 'Worst',
            ],
        ];
    }
    public static function getActiveCoursesForTeacher($teacher_id)
    {
        try {

            $currentSessionId = (new session())->getCurrentSessionId();
            $assignments = teacher_offered_courses::where('teacher_id', $teacher_id)
                ->with(['offeredCourse.course', 'section'])
                ->get();
            $activeCourses = [];
            foreach ($assignments as $assignment) {
                $offeredCourse = $assignment->offeredCourse;
                if (!$offeredCourse) {
                    continue;
                }
                $sessionId = $offeredCourse->session_id;
                if ($sessionId == $currentSessionId) {
                    $activeCourses[] = [
                        'course_name' => $offeredCourse->course->name,
                        'teacher_offered_course_id' => $assignment->id,
                        'section_name' => $assignment->section->getNameByID($assignment->section_id),
                    ];
                }
            }
            return $activeCourses;
        } catch (Exception $ex) {
            return [
                'error' => 'An error occurred while fetching the active courses.',
                'message' => $ex->getMessage(),
            ];
        }
    }
    public function YourTaskInfo(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $task = self::categorizeTasksForTeacher($teacher_id);
            return response()->json([
                'status' => 'success',
                'Tasks' => $task,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public static function categorizeTasksForTeacher(int $teacher_id)
    {
        try {
            $activeCourses = self::getActiveCoursesForTeacher($teacher_id);
            $teacherOfferedCourseIds = collect($activeCourses)->pluck('teacher_offered_course_id');
            $tasks = \App\Models\task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->whereIn('teacher_offered_course_id', $teacherOfferedCourseIds)
                ->where('CreatedBy', 'Teacher')
                ->get();
            $completedTasks = [];
            $upcomingTasks = [];
            $ongoingTasks = [];
            $unMarkedTasks = [];
            foreach ($tasks as $task) {
                $currentDate = Carbon::now();
                $startDate = Carbon::parse($task->start_date);
                $dueDate = Carbon::parse($task->due_date);
                $markingInfo = null;
                if ($task->isMarked) {
                    $markingInfo = self::getMarkingInfo($task->id);
                }
                $graderTask = grader_task::where('task_id', $task->id)->with(['grader.student'])->first();
                if ($graderTask) {
                    $Assigned = "Yes";
                    $message = "You Assigned This Task to Grader {$graderTask->grader->student->name}/({$graderTask->grader->student->RegNo}) For Evaluation !";
                } else {
                    $Assigned = "No";
                    $message = "No Grader For this Task is Allocated By You";
                }
                $taskInfo = [
                    'task_id' => $task->id,
                    'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                    'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                    'title' => $task->title,
                    'type' => $task->type,
                    ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                        ? Action::getMCQS($task->courseContent->id)
                        : FileHandler::getFileByPath($task->courseContent->content),
                    'created_by' => $task->CreatedBy,
                    'points' => $task->points,
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'marking_status' => $task->isMarked ? 'Marked' : 'Un-Marked',
                    'marking_info' => $markingInfo ?? 'Not-Marked',
                    'Is Allocated To Grader' => $Assigned,
                    'Grader Info For this Task' => $message
                ];
                if ($task->isMarked) {
                    $completedTasks[] = $taskInfo;
                } elseif ($startDate > $currentDate) {
                    $upcomingTasks[] = $taskInfo;
                } elseif ($startDate <= $currentDate && $dueDate >= $currentDate) {
                    $ongoingTasks[] = $taskInfo;
                } elseif ($dueDate < $currentDate && !$task->isMarked) {
                    $unMarkedTasks[] = $taskInfo;
                }
            }
            return [
                'completed_tasks' => $completedTasks,
                'upcoming_tasks' => $upcomingTasks,
                'ongoing_tasks' => $ongoingTasks,
                'unmarked_tasks' => $unMarkedTasks,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred while categorizing tasks.',
                'error' => $e->getMessage(),
            ];
        }
    }

    public static function getUnmarkedNonMCQTasksWithoutGrader(int $teacher_id)
    {
        try {
            $activeCourses = self::getActiveCoursesForTeacher($teacher_id);
            $teacherOfferedCourseIds = collect($activeCourses)->pluck('teacher_offered_course_id');
            $tasks = \App\Models\task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])
                ->whereIn('teacher_offered_course_id', $teacherOfferedCourseIds)
                ->where('CreatedBy', 'Teacher')
                ->where('isMarked', false)
                ->whereHas('courseContent', function ($query) {
                    $query->where('content', '!=', 'MCQS');
                })
                ->get();
            $unmarkedNonMCQTasksWithoutGrader = [];
            foreach ($tasks as $task) {
                $graderTask = grader_task::where('task_id', $task->id)->first();
                if (!$graderTask) {
                    $taskInfo = [
                        'task_id' => $task->id,
                        'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                        'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                        'title' => $task->title,
                        'type' => $task->type,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date
                    ];
                    $unmarkedNonMCQTasksWithoutGrader[] = $taskInfo;
                }
            }

            return $unmarkedNonMCQTasksWithoutGrader;
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'An unexpected error occurred while retrieving tasks.',
                'error' => $e->getMessage(),
            ];
        }
    }
    public function UnAssignedTaskToGrader(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $unasgTask = self::getUnmarkedNonMCQTasksWithoutGrader($teacher_id);
            return response()->json([
                'status' => 'success',
                'Tasks' => $unasgTask,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function assignTaskToGrader(Request $request)
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|exists:tasks,id',
                'grader_id' => 'required|exists:graders,id',
            ]);
            $existingAssignment = grader_task::where('Task_id', $request->task_id)
                ->where('Grader_id', $request->grader_id)
                ->first();
            if ($existingAssignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This task is already assigned to the selected grader.',
                ], 400);
            }
            $graderTask = grader_task::create([
                'Task_id' => $request->task_id,
                'Grader_id' => $request->grader_id,
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Task successfully assigned to the grader.',
                'data' => $graderTask,
            ], 201);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAssignedGraders(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $currentSessionId = (new Session())->getCurrentSessionId();
            $teacherGraders = teacher_grader::where('teacher_id', $teacher_id)->get();
            $activeGraders = [];
            $previousGraders = [];
            foreach ($teacherGraders as $teacherGrader) {
                $grader = grader::find($teacherGrader->grader_id);
                if ($grader) {
                    $graderDetails = [
                        'id' => $grader->id,
                        'RegNo' => $grader->student->RegNo,
                        'name' => $grader->student->name,
                        'status' => $grader->status,
                        'feedback' => $teacherGrader->feedback,
                    ];
                    if ($teacherGrader->session_id == $currentSessionId) {
                        $activeGraders[] = $graderDetails;
                    } else {
                        $previousGraders[] = $graderDetails;  // In previous sessions
                    }
                }
            }
            return response()->json([
                'status' => 'success',
                'active_graders' => $activeGraders,
                'previous_graders' => $previousGraders,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    public function getListofUnassignedTask(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $activeCourses = self::getActiveCoursesForTeacher($teacher_id);
            $unassignedTasks = [];
            foreach ($activeCourses as $singleSection) {
                $offered_course_id = teacher_offered_courses::find($singleSection['teacher_offered_course_id']);
                $courseContents = coursecontent::where('offered_course_id', $offered_course_id->id)
                    ->whereIn('type', ['Assignment', 'Quiz', 'LabTask'])
                    ->get();
                
                $taskIds = \App\Models\task::where('teacher_offered_course_id', $singleSection['teacher_offered_course_id'])
                    ->pluck('coursecontent_id');
                $missingTasks = $courseContents->filter(function ($courseContent) use ($taskIds) {
                    return !$taskIds->contains($courseContent->id);
                });
                if ($missingTasks->isNotEmpty()) {
                    $customMissingTasks = $missingTasks->map(function ($task) {
                        return [
                            'course_content_id' => $task->id,
                            'title' => $task->title,
                            'type' => $task->type,
                            'week' => $task->week,
                            'offered_course_id' => $task->offered_course_id,
                            ($task->content == 'MCQS') ? 'MCQS' : 'File' => ($task->content == 'MCQS')
                        ? Action::getMCQS($task->id)
                        : FileHandler::getFileByPath($task->content),
                            
                        ];
                    });
                
                    $unassignedTasks[] = [
                        'teacher_offered_course_id' => $singleSection['teacher_offered_course_id'],
                        'section_name' => $singleSection['section_name'],
                        'unassigned_tasks' => $customMissingTasks->toArray(), 
                    ];
                }
            }
            return response()->json([
                'status' => 'success',
                'unassigned_tasks' => $unassignedTasks,
            ], 200);

        } catch (Exception $e) {
            // Handle any errors
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching unassigned tasks.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function storeTask(Request $request)
    {
        try {
            $validatedData = $request->validate([
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
               
                if (!$section||$section==0) {
                    $insertedTasks[]=[
                        'status' => 'error',
                        'message' => "Section '{$sectionName}' not found.",
                    ];
                    continue;
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
                    $insertedTasks[]=[
                        'status' => 'error',
                        'message' => "Teacher-offered course not found for section '{$sectionName}' and course '{$courseName}'.",
                    ];
                    continue;
                }
               
                $taskNo = Action::getTaskCount($teacherOfferedCourse->id, $type);
                if ($taskNo > 0 && $taskNo < 10) {
                    $taskNo = "0" . $taskNo;
                }
                
                $filename = $course->description.'-' . $type . $taskNo . '-' . $sectionName;
                $title = $filename;
                $teacherOfferedCourseId = $teacherOfferedCourse->id;
                
                $taskData = [
                    'title' => $title,
                    'type' => $type,
                    'CreatedBy' => 'Teacher',
                    'points' => $points,
                    'start_date' => $startDate,
                    'due_date' => $dueDate,
                    'coursecontent_id' => $coursecontent_id,
                    'teacher_offered_course_id' => $teacherOfferedCourseId,
                    'isMarked' => false,
                ];
               
                $task=\App\Models\task::where('teacher_offered_course_id',$teacherOfferedCourse)
                ->where('coursecontent_id',$coursecontent_id)->first();
                
                if($task){
                    $task->update($taskData);
                    $insertedTasks[] = ['status'=>'Task is Already Allocated ! Just Updated the informations','task'=>$task];
                }else{
                    $task = \App\Models\task::create($taskData);
                    $insertedTasks[] = ['status'=>'Task Allocated Successfully','task'=>$task];
                }
               
            }
            return response()->json([
                'status' => 'success',
                'message' => 'Tasks inserted successfully.',
                'tasks' => $insertedTasks,
            ], 200);
        } catch (ValidationException $e) {
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
}
