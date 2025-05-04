<?php

namespace App\Http\Controllers;
use App\Models\FileHandler;
use App\Models\teacher;
use Illuminate\Support\Facades\Storage;
use App\Models\attendance;
use App\Models\course;
use App\Models\grader;
use App\Models\grader_task;
use App\Models\notification;
use App\Models\offered_courses;
use App\Models\section;
use App\Models\sessionresult;
use App\Models\student;
use App\Models\student_offered_courses;
use App\Models\student_task_result;
use App\Models\student_task_submission;
use App\Models\task;
use App\Models\teacher_offered_courses;
use App\Models\timetable;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\select;
class GraderController extends Controller
{
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
            $task_id = $request->task_id;
            $Logs = [];
    
            foreach ($submissions as $submission) {
                try {
                    $student_id = $submission['student_id'];
                    $obtainedMarks = $submission['obtainedMarks'];
    
                    $result = self::storeOrUpdateResult($task_id, $student_id, $obtainedMarks);
                    if (!$result) {
                        $Logs[] = ["Message" => "Error in uploading marks for Student ID: $student_id", "Data" => $submission];
                    } else {
                        $Logs[] = ["Message" => "Successfully uploaded marks for Student ID: $student_id", "Data" => $submission];
                    }
                    //$Logs[]=$result;
                } catch (Exception $ex) {
                    $Logs[] = ["Message" => "Exception occurred for Student ID: $student_id", "Error" => $ex->getMessage(), "Data" => $submission];
                }
            }
    
            if ($task_id) {
                task::ChangeStatusOfTask($task_id);
            }
    
            return response()->json([
                'message' => 'Submissions processed with individual error handling!',
                'data' => $Logs
            ], 200);
    
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while processing submissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    public static function storeOrUpdateResult($task_id, $student_id, $obtainedMarks)
    {
        try {
            $result = student_task_result::where('Task_id', $task_id)
                ->where('Student_id', $student_id)
                ->first();
    
            if ($result) {
                DB::table('student_task_result')
                ->where('Task_id', $task_id)
                ->where('Student_id', $student_id)
                ->update(['ObtainedMarks' => $obtainedMarks]);
            } else {
                    DB::table('student_task_result')->insert([
                    'ObtainedMarks' => $obtainedMarks,
                    'Task_id' => $task_id,
                    'Student_id' => $student_id
                ]);
            }
    
            return true;
        } catch (Exception $ex) {
            return null; 
        }
    }
    
    // public function SubmitNumberList(Request $request)
    // {
    //     try {
    //         $submissions = $request->submissions;
    //         $task_id = $request->task_id;
    //         $Logs = [];
    //         foreach ($submissions as $submission) {
    //             $student_RegNo = $submission['regNo'];
    //             $obtainedMarks = $submission['obtainedMarks'];
    //             $result = student_task_result::storeOrUpdateResult($task_id, $student_RegNo, $obtainedMarks);
    //             if (!$result) {
    //                 $Logs[] = ["Message" => "Error in Uploading the Number of $student_RegNo", "Data" => $submission];
    //             } else {
    //                 $Logs[] = ["Message" => "successfully Uploaded the Number of $student_RegNo", "Data" => $submission];
    //             }
    //         }
    //         if ($task_id) {
    //             task::ChangeStatusOfTask($task_id);
    //         }
    //         return response()->json([
    //             'message' => 'All submissions processed successfully!',
    //             'data' => $Logs
    //         ], 200);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'An unexpected error occurred',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    //task_id;
    public function ListOfStudentForTask(Request $request)
    {
        try {
            $task_id = $request->task_id;
            $task = task::find($task_id);
            if ($task) {
                $section = (new task())->getSectionIdByTaskId($task_id);
                if (!$section) {
                    throw new Exception('No Section Found For this Task' . $section);
                }
                $students = Student::select('student.id', 'student.name', 'student.RegNo')
                    ->join('student_offered_courses', 'student.id', '=', 'student_offered_courses.student_id')
                    ->join('offered_courses', 'student_offered_courses.offered_course_id', '=', 'offered_courses.id')
                    ->where('student_offered_courses.section_id', $section)
                    ->where('offered_courses.session_id', (new session())->getCurrentSessionId())->distinct()
                    ->get();
                $submissions = student_task_submission::select(
                    'student_task_submission.Student_id',
                    'student.name',
                    'student.RegNo',
                    'student_task_submission.Answer'
                )
                    ->join('student', 'student_task_submission.Student_id', '=', 'student.id')
                    ->whereIn('student_task_submission.Student_id', $students->pluck('id')) // Extract IDs from $students collection
                    ->where('student_task_submission.Task_id', $task_id)
                    ->get();
                    $results = student_task_result::select(
                        'Student_id',
                        'ObtainedMarks'
                    )
                        ->whereIn('Student_id', $students->pluck('id'))
                        ->where('Task_id', $task_id)
                        ->get();
                $result = $students->map(function ($student) use ($submissions,$results) {
                    $submission = $submissions->firstWhere('Student_id', $student->id);
                    $resultData = $results->firstWhere('Student_id', $student->id);
                    return (object) [
                        'Student_id' => $student->id,
                        'name' => $student->name,
                        'RegNo' => $student->RegNo,
                        'Answer' => $submission ? asset($submission->Answer) : null,
                        'ObtainedMarks' => $resultData ? $resultData->ObtainedMarks : null
                    ];

                });
                return response()->json(
                    [
                        'message' => 'Fetched Successfully',
                        'assigned Tasks' => $result
                    ],
                    200
                );
            } else {
                throw new Exception('No Info Exsist ! ');
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function GraderTask(Request $request)
    {
        try {
            $graderId = $request->grader_id;
            if (!$graderId) {
                throw new Exception('Please Provide with the Grader ID');
            }
            $session_id = (new session())->getCurrentSessionId();
            $assignedTasks = grader_task::with(['task', 'task.teacherOfferedCourse.section', 'task.teacherOfferedCourse.offeredCourse.course', 'task.teacherOfferedCourse.offeredCourse.session', 'task.courseContent'])
                ->where('Grader_id', $graderId)
                ->whereHas('task.teacherOfferedCourse.offeredCourse.session', function ($query) use ($session_id) {
                    $query->where('id', $session_id);
                })
                ->get()
                ->map(function ($graderTask) {
                    $task = $graderTask->task;
                    $markingInfo = null;
                    $taskdetails = task::with(['teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->find($task->id);

                    if ($task->isMarked) {
                        $markingInfo = $this->getMarkingInfo($task->id);
                        $Teacher = teacher::find($taskdetails->teacherOfferedCourse->teacher_id);
                        return [
                            'task_id' => $task->id,
                            'Course Name' => $taskdetails->teacherOfferedCourse->offeredCourse->course->name,
                            'Section Name' => (new section())->getNameByID($taskdetails->teacherOfferedCourse->section->id),
                            'Teacher Name' => $Teacher->name ?? null,
                            'title' => $task->title,
                            'type' => $task->type,
                            'File' => $task->courseContent ? asset($task->courseContent->content) : 'null',
                            'created_by' => $task->CreatedBy,
                            'Total Marks' => $task->points,
                            'start_date' => $task->start_date,
                            'due_date' => $task->due_date,
                            'marking_status' => 'Marked',
                            'marking_info' => $markingInfo,
                        ];
                    } else {
                        $Teacher = teacher::find($taskdetails->teacherOfferedCourse->teacher_id);
                        return [
                            'task_id' => $task->id,
                            'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                            'Section Name' => (new section())->getNameByID($task->teacherOfferedCourse->section->id),
                            'Teacher Name' => $Teacher->name ?? null,
                            'title' => $task->title,
                            'type' => $task->type,
                            'File' => $task->courseContent ? asset($task->courseContent->content) : 'null',
                            'created_by' => $task->CreatedBy,
                            'Total Marks' => $task->points,
                            'start_date' => $task->start_date,
                            'due_date' => $task->due_date,
                            'marking_status' => 'Un-Marked',
                            'points' => $task->points,
                        ];
                    }
                });

                $currentDate = now();

                $preAssignedTasks = $assignedTasks->filter(function ($task) use ($currentDate) {
                    return $task['start_date'] > $currentDate || 
                           ($task['start_date'] <= $currentDate && $task['due_date'] >= $currentDate);
                });
                
                $completedTasks = $assignedTasks->filter(function ($task) use ($currentDate) {
                    return $task['marking_status'] === 'Marked' || 
                           ($task['marking_status'] !== 'Marked' && $task['due_date'] < $currentDate);
                });
                
                return response()->json(
                    [
                        'message' => 'Fetched Successfully',
                        'PreAssignedTasks' => $preAssignedTasks->values(),
                        'CompletedTasks' => $completedTasks->values(),
                    ],
                    200
                );
                
            // $currentDate = now();
            // $markedTasks = $assignedTasks->filter(function ($task) {
            //     return $task['marking_status'] === 'Marked';
            // });
            // $upcomingTasks = $assignedTasks->filter(function ($task) use ($currentDate) {
            //     return $task['start_date'] > $currentDate;
            // });

            // $ongoingTasks = $assignedTasks->filter(function ($task) use ($currentDate) {
            //     return $task['start_date'] <= $currentDate && $task['due_date'] >= $currentDate;
            // });

            // $unmarkedTasks = $assignedTasks->filter(function ($task) use ($currentDate) {
            //     return !$task['marking_status'] === 'Marked' && $task['due_date'] < $currentDate;
            // });
            // return response()->json(
            //     [
            //         'message' => 'Fetched Successfully',
            //         'MarkedTask' => $markedTasks->values(),
            //         'UpcomingTask' => $upcomingTasks->values(),
            //         'OngoingTask' => $ongoingTasks->values(),
            //         'UnMarkedTask' => $unmarkedTasks->values(),
            //     ],
            //     200
            // );
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    private function getMarkingInfo($taskId)
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
        $midPoint = ($topMark->obtained_marks + $worstMark->obtained_marks) / 2;

    // Find student with marks closest to the midpoint
    $averageMark = $marks->sortBy(function ($item) use ($midPoint) {
        return abs($item->obtained_marks - $midPoint);
    })->first();

        return [
            'top' => [
                'student_name' => $topMark->student_name,
                'obtained_marks' => $topMark->obtained_marks,
                'title' => 'Good',
            ],
            'average' => [
                'student_name' => $averageMark->student_name,
            'obtained_marks' => $averageMark->obtained_marks,
            'title' => 'Average',
            ],
            'worst' => [
                'student_name' => $worstMark->student_name,
                'obtained_marks' => $worstMark->obtained_marks,
                'title' => 'Worst',
            ],
        ];
    }
    //student_id;
    public function GraderOf(Request $request)
    {
        try {
            $student_id = $request->student_id;
            $currentSessionId = (new session())->getCurrentSessionId();
            $graders = DB::table('grader')
                ->join('student', 'grader.student_id', '=', 'student.id')
                ->join('teacher_grader', 'grader.id', '=', 'teacher_grader.grader_id')
                ->join('teacher', 'teacher_grader.teacher_id', '=', 'teacher.id')
                ->join('session', 'teacher_grader.session_id', '=', 'session.id')
                ->where('grader.student_id', $student_id)
                ->select(
                    'grader.id as id',
                    'student.name as name',
                    'student.image as image',
                    'student.section_id as section',
                    'student.RegNo as regno',
                    'grader.status',
                    'grader.type',
                    'teacher.name as teacher_name',
                    'teacher.image as teacher_image',
                    'teacher_grader.feedback',
                    'session.id as  session_id',
                    DB::raw("CONCAT(session.name,'-',session.year) as session"),
                    'session.id as session_id',
                    DB::raw("CASE WHEN session.id = $currentSessionId THEN 'active' ELSE 'non-active' END as session_status"),
                    DB::raw("CASE WHEN session.id = $currentSessionId THEN NULL ELSE teacher_grader.feedback END as feedback")
                )
                ->get()
                ->groupBy('grader_id')
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'grader_id' => $first->id,
                        'grader_name' => $first->name,
                        'grader_RegNo' => $first->regno,
                        'image' => $first->image?asset($first->image):null,
                        'grader_section' => (new section())->getNameByID($first->section),
                        'This Session' => $first->status,
                        'type' => $first->type,
                        'Grader Allocations' => $group->map(function ($item) {
                            return [
                                'Session is ? ' => $item->session_id === (new session())->getCurrentSessionId() ? 'Current Session' : 'Previous Session',
                                'teacher_name' => $item->teacher_name,
                                'session_name' => $item->session,
                                'teacher_image' => $item->teacher_image?asset($item->teacher_image):null,
                                'Allocation Status' => $item->session_status,
                                'feedback' => (!empty($item->feedback) || $item->feedback === '0') ? $item->feedback : 'Not Added By Instructor',
                            ];
                        }),
                    ];
                });

            return response()->json([
                'message' => 'Data Fetched Successfully',
                'data' => $graders->values(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
