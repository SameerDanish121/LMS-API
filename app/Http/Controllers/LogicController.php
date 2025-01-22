<?php

namespace App\Http\Controllers;

use App\Models\quiz_questions;
use App\Models\student;
use App\Models\student_task_result;
use App\Models\task;
use App\Models\task_consideration;
use App\Models\teacher_offered_courses;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
class LogicController extends Controller
{
    // public static function getTaskConsiderationsForStudent($studentId, $teacherOfferedCourseId)
    // {
    //     $taskConsideration = task_consideration::where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
    //     $teacherOfferedCourse = teacher_offered_courses::with(['offeredCourse.course', 'offeredCourse', 'section'])->find($teacherOfferedCourseId);
    //     $student = student::find($studentId);
    //     if (!$teacherOfferedCourse) {
    //         return [
    //             'status' => 'error',
    //             'message' => 'Record For Teacher & Section is Not Found.',
    //         ];
    //     }
    //     if ($taskConsideration->isEmpty()) {
    //         return [
    //             'status' => 'error',
    //             'message' => 'No consideration is set by the teacher.',
    //         ];
    //     }
    //     $categorizedTasks = [
    //         'Quiz' => [],
    //         'Assignment' => [],
    //         'LabTask' => [],
    //     ];
    //     foreach ($taskConsideration as $taskScenario) {
    //         $top = $taskScenario->top;
    //         $jlConsiderCount = $taskScenario->jl_consider_count;
    //         $type = $taskScenario->type;
    //         if ($jlConsiderCount === null) {
    //             $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
    //                 ->where('CreatedBy', 'Teacher')
    //                 ->where('type', $type)
    //                 ->get()
    //                 ->map(function ($task) use ($studentId) {
    //                     $obtainedMarks = student_task_result::where('Task_id', $task->id)
    //                         ->where('Student_id', $studentId)
    //                         ->first()->ObtainedMarks ?? 0;
    //                     $task->obtained_marks = $obtainedMarks;
    //                     return $task;
    //                 })
    //                 ->sortByDesc('obtained_marks')
    //                 ->take($top);
    //         } else {
    //             $teacherCount = $top - $jlConsiderCount;
    //             $jlCount = $jlConsiderCount;
    //             $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
    //                 ->where('CreatedBy', 'Teacher')
    //                 ->where('type', $type) 
    //                 ->get()
    //                 ->map(function ($task) use ($studentId) {
    //                     $obtainedMarks = student_task_result::where('Task_id', $task->id)
    //                         ->where('Student_id', $studentId)
    //                         ->first()->ObtainedMarks ?? 0;
    //                     $task->obtained_marks = $obtainedMarks;
    //                     return $task;
    //                 })
    //                 ->sortByDesc('obtained_marks')
    //                 ->take($teacherCount);
    //             $juniorTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
    //                 ->where('CreatedBy', 'JuniorLecturer')
    //                 ->where('type', $type)
    //                 ->get()
    //                 ->map(function ($task) use ($studentId) {
    //                     $obtainedMarks = student_task_result::where('Task_id', $task->id)
    //                         ->where('Student_id', $studentId)
    //                         ->first()->ObtainedMarks ?? 0;
    //                     $task->obtained_marks = $obtainedMarks;
    //                     return $task;
    //                 })
    //                 ->sortByDesc('obtained_marks')
    //                 ->take($jlCount);
    //         }
    //         if (!empty($teacherTasks)) {
    //             $categorizedTasks[$type]['Teacher'] = $teacherTasks;
    //         }
    //         if (!empty($juniorTasks)) {
    //             $categorizedTasks[$type]['JuniorLecturer'] = $juniorTasks;
    //         }
    //     }
    //     return [
    //         'status' => 'success',
    //         'teacher_offered_course_id' => $teacherOfferedCourseId,
    //         'tasks' => $categorizedTasks
    //     ];
    // }



    public static function getTaskConsiderationsForStudent($studentId, $teacherOfferedCourseId)
    {
        $taskConsideration = task_consideration::where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
        $teacherOfferedCourse = teacher_offered_courses::with(['offeredCourse.course', 'offeredCourse', 'section'])->find($teacherOfferedCourseId);
        $student = student::find($studentId);
        
        if (!$teacherOfferedCourse) {
            return [
                'status' => 'error',
                'message' => 'Record For Teacher & Section is Not Found.',
            ];
        }
        if($taskConsideration->isEmpty()) {
            return [
                'status' => 'error',
                'message' => 'No consideration is set by the teacher.',
            ];
        }

        $categorizedTasks = [
            'Quiz' => [],
            'Assignment' => [],
            'LabTask' => [],
        ];

        foreach ($taskConsideration as $taskScenario) {
            $top = $taskScenario->top;
            $jlConsiderCount = $taskScenario->jl_consider_count;
            $type = $taskScenario->type;
            $teacherTasks = [];
            $juniorTasks = [];
            if ($jlConsiderCount === null) {
                $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('CreatedBy', 'Teacher')
                    ->where('type', $type)
                    ->get()
                    ->map(function ($task) use ($studentId) {
                        $obtainedMarks = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $studentId)
                            ->first()->ObtainedMarks ?? 0;
                        $task->obtained_marks = $obtainedMarks;
                        return $task;
                    })
                    ->sortByDesc('obtained_marks')
                    ->take($top);
                $teacherTasks = $tasks;
            } else {
                // Teacher and JuniorLecturer Tasks
                $teacherCount = $top - $jlConsiderCount;
                $jlCount = $jlConsiderCount;

                // Teacher tasks
                $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('CreatedBy', 'Teacher')
                    ->where('type', $type)
                    ->get()
                    ->map(function ($task) use ($studentId) {
                        $obtainedMarks = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $studentId)
                            ->first()->ObtainedMarks ?? 0;
                        $task->obtained_marks = $obtainedMarks;
                        return $task;
                    })
                    ->sortByDesc('obtained_marks')
                    ->take($teacherCount);
                $juniorTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('CreatedBy', 'JuniorLecturer')
                    ->where('type', $type)
                    ->get()
                    ->map(function ($task) use ($studentId) {
                        $obtainedMarks = student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $studentId)
                            ->first()->ObtainedMarks ?? 0;
                        $task->obtained_marks = $obtainedMarks;
                        return $task;
                    })
                    ->sortByDesc('obtained_marks')
                    ->take($jlCount);
            }
            if (!empty($teacherTasks)) {
                $categorizedTasks[$type]['Teacher'] = $teacherTasks;
            }
            if (!empty($juniorTasks)) {
                $categorizedTasks[$type]['JuniorLecturer'] = $juniorTasks;
            }
        }
        $response = [
            'status' => 'success',
            'teacher_offered_course_id' => $teacherOfferedCourseId,
            'tasks' => $categorizedTasks,
        ];
        foreach ($categorizedTasks as $type => $tasks) {
            $teacherTasks = $tasks['Teacher'] ?? [];
            $juniorTasks = $tasks['JuniorLecturer'] ?? [];

            if (!empty($teacherTasks)) {
                $response['message'] = "Best {$type} tasks for Teacher are listed.";
            }

            if (!empty($juniorTasks)) {
                $response['message'] .= " Best {$type} tasks for JuniorLecturer are listed.";
            }

            // If there are no tasks in any category, ensure no message is appended
            if (empty($teacherTasks) && empty($juniorTasks)) {
                $response['message'] = "No tasks found for {$type}.";
            }
        }

        return $response;
    }

    // public function getTaskConsiderations(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         'student_id' => 'required',
    //         'teacher_offered_course_id' =>'required',
    //     ]);
    //     if ($validator->fails()) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $validator->errors()->first()
    //         ], 400);
    //     }

    //     $studentId = $request->input('student_id');
    //     $teacherOfferedCourseId = $request->input('teacher_offered_course_id');
    //     $result = self::getTaskConsiderationsForStudent($studentId, $teacherOfferedCourseId);
    //     if ($result['status'] === 'success') {
    //         return response()->json([
    //             'status' => 'success',
    //             'teacher_offered_course_id' => $result['teacher_offered_course_id'],
    //             'tasks' => $result['tasks']
    //         ]);
    //     }
    //     return response()->json([
    //         'status' => 'error',
    //         'message' => $result['message']
    //     ], 400);
    // }


    public function getTaskConsiderations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required|integer',
            'teacher_offered_course_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 400);
        }
        $studentId = $request->input('student_id');
        $teacherOfferedCourseId = $request->input('teacher_offered_course_id');
        $result = self::getTaskConsiderationsForStudent($studentId, $teacherOfferedCourseId);
        if ($result['status'] === 'success') {
            return response()->json([
                'status' => 'success',
                'message' => $this->generateTaskConsiderationMessage($result['tasks']),
                'teacher_offered_course_id' => $result['teacher_offered_course_id'],
                'tasks' => $result['tasks'],
            ]);
        }
        return response()->json([
            'status' => 'error',
            'message' => $result['message']
        ], 400);
    }
    private function generateTaskConsiderationMessage($tasks)
    {
        $messages = [];

        foreach ($tasks as $type => $taskData) {
            $teacherTasks = $taskData['Teacher'] ?? [];
            $juniorTasks = $taskData['JuniorLecturer'] ?? [];

            // Generate message for Teacher tasks
            if (!empty($teacherTasks)) {
                $messages[] = "Best {$type} tasks for Teacher are listed.";
            }

            // Generate message for JuniorLecturer tasks
            if (!empty($juniorTasks)) {
                $messages[] = "Best {$type} tasks for JuniorLecturer are listed.";
            }

            // If no tasks in either category, append a default message
            if (empty($teacherTasks) && empty($juniorTasks)) {
                $messages[] = "No tasks found for {$type}.";
            }
        }

        return implode(" ", $messages);
    }
    public function Sample(Request $request)
    {
        try {
            return self::calculateQuizMarks($request->Answer,36);
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
    public static function calculateQuizMarks($answers,$coursecontent_id)
    {
        $totalMarks = 0;
        foreach ($answers as $answer) {
            $questionNo = $answer['QNo']; 
            $studentAnswer = $answer['StudentAnswer'];
            $question = quiz_questions::with('Options')->where('coursecontent_id',$coursecontent_id)->where('question_no',$questionNo)->first();
            if ($question) {
                $correctOption = $question->Options->firstWhere('is_correct', true);
                if ($correctOption && $studentAnswer === $correctOption->option_text) {
                    $totalMarks += $question->points;
                }
            }
        }
        return $totalMarks;
    }
    
 







}
