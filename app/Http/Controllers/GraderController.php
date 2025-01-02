<?php

namespace App\Http\Controllers;
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
            $task_id=$request->task_id;
            $student_RegNo=$request->regNo;
            $obtainedMarks=$request->obtainedMarks;
            if($task_id){
                grader_task::markTaskAsGraded($task_id);
            }
            $result = student_task_result::storeOrUpdateResult($task_id, $student_RegNo, $obtainedMarks);
            if ($result) {
                return response()->json(
                    [
                        'message' => 'OK',
                    ],
                    200
                );
            }else {
               throw new Exception('Unexpected Error Occurs !!!!! ');
            }
        }catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
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
                    ->where('offered_courses.session_id', (new session())->getCurrentSessionId())
                    ->get()->unique('student.RegNo');
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
                $result = $students->map(function ($student) use ($submissions) {
                    $submission = $submissions->firstWhere('Student_id', $student->id);
                    if ($submission) {
                        // Ensure the path is correctly formatted
                        $relativePath = str_replace('public/', '', $submission->Answer);

                        // Check if the file exists and retrieve its content
                        if (Storage::disk('public')->exists($relativePath)) {
                            // Fetch the file content (convert to base64 if needed)
                            $submission->Answer = base64_encode(Storage::disk('public')->get($relativePath));
                        } else {
                            // If the file does not exist, set Answer to null
                            $submission->Answer = null;
                        }
                        // Return the submission
                        return $submission;
                    } else {
                        // If no submission exists, return a placeholder object
                        return (object) [
                            'Student_id' => $student->id,
                            'name' => $student->name,
                            'RegNo' => $student->RegNo,
                            'Answer' => null,
                        ];
                    }


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
            $session_id = (new session())->getCurrentSessionId();
            $assignedTasks = grader_task::with(['task', 'task.teacherOfferedCourse.offeredCourse.session'])
                ->where('Grader_id', $graderId)
                ->whereHas('task.teacherOfferedCourse.offeredCourse.session', function ($query) use ($session_id) {
                    $query->where('id', $session_id);
                })
                ->get()
                ->map(function ($graderTask) {
                    $task = $graderTask->task;
                    $markingInfo = null;
                    if ($graderTask->isMarked) {
                        $markingInfo = $this->getMarkingInfo($task->id);
                    }
                    return [
                        'task_id' => $task->id,
                        'title' => $task->title,
                        'type' => $task->type,
                        'path' => $task->path,
                        'created_by' => $task->CreatedBy,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        'is_evaluated' => $task->IsEvaluated,
                        'teacher_offered_course' => $task->teacherOfferedCourse ? $task->teacherOfferedCourse->name : null,
                        'marking_status' => $graderTask->isMarked ? 'Marked' : 'Un-Marked',
                        'marking_info' => $markingInfo,
                    ];
                });

            return response()->json(
                [
                    'message' => 'Fetched Successfully',
                    'Student To Be Marked' => $assignedTasks
                ],
                200
            );
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

        // Get the worst performer (last in the ordered collection)
        $worstMark = $marks->last();

        // Calculate the average marks of all submissions
        $averageMarks = $marks->avg('obtained_marks');

        return [
            'top' => [
                'student_name' => $topMark->student_name,
                'obtained_marks' => $topMark->obtained_marks,
                'title' => 'Good',  // Top performer title
            ],
            'average' => [
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
                    'grader.status',
                    'grader.type',
                    'teacher.name as teacher_name',
                    'teacher.image as teacher_image',
                    'teacher_grader.feedback',
                    DB::raw("CONCAT(session.name, ' - ', session.year) as session"),
                    'session.id as session_id',
                    DB::raw("CASE WHEN session.id = $currentSessionId THEN 'active' ELSE 'non_active' END as session_status"),
                    DB::raw("CASE WHEN session.id = $currentSessionId THEN NULL ELSE teacher_grader.feedback END as feedback")
                )
                ->get()
                ->groupBy('grader_id')
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'grader_id' => $first->id,
                        'grader_name' => $first->name,
                        'status' => $first->status,
                        'type' => $first->type,
                        'GraderOf' => $group->map(function ($item) {
                            return [
                                'teacher_name' => $item->teacher_name,
                                'status' => $item->status,
                                'feedback' => $item->feedback,
                                'session_name' => $item->name,
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
