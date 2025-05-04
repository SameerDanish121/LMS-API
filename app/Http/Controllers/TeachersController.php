<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\admin;
use App\Models\Attendance_Sheet_Sequence;
use App\Models\coursecontent;
use App\Models\coursecontent_topic;
use App\Models\datacell;
use App\Models\dayslot;
use App\Models\exam;
use App\Models\excluded_days;
use App\Models\FileHandler;
use App\Models\grader;
use App\Models\grader_task;
use App\Models\juniorlecturer;
use App\Models\JuniorLecturerHandling;
use App\Models\program;
use App\Models\question;
use App\Models\quiz_questions;
use App\Models\role;
use App\Models\student_exam_result;
use App\Models\StudentManagement;
use App\Models\t_coursecontent_topic_status;
use App\Models\task_consideration;
use App\Models\teacher;
use App\Models\teacher_grader;
use App\Models\teacher_juniorlecturer;
use App\Models\temp_enroll;
use App\Models\topic;
use App\Models\venue;
use Exception;
use GrahamCampbell\ResultType\Success;
use Laravel\Pail\Options;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
use App\Models\attendance;
use App\Models\course;
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
use DateTime;
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\isEmpty;
use App\Models\contested_attendance;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
class TeachersController extends Controller
{
    public function getConsiderationSummary(Request $request){
        $request->validate([
            'teacher_offered_course_id' => 'required',
        ]);
        $toc = teacher_offered_courses::with('offeredCourse.course')->find($request->teacher_offered_course_id);
        if (!$toc || !$toc->offeredCourse || !$toc->offeredCourse->course) {
            return response()->json(['error' => 'Course not properly linked.'], 404);
        }
        $course = $toc->offeredCourse->course;
        $isLab = $course->lab == 1;
        $summary=task_consideration::getConsiderationSummary($request->teacher_offered_course_id,$isLab)??[];
        return response()->json([
            'status' => 'success',
            'summary' => $summary
        ], 200);
    }
    public function storeConsideration(Request $request)
    {
    $request->validate([
        'teacher_offered_course_id' => 'required',
        'data' => 'required|array',
    ]);
    $errors = [];
    $tocId = $request->teacher_offered_course_id;
    $data = $request->data;
    try {
        $toc = teacher_offered_courses::with('offeredCourse.course')->find($tocId);
        if (!$toc || !$toc->offeredCourse || !$toc->offeredCourse->course) {
            return response()->json(['error' => 'Course not properly linked.'], 404);
        }
        $course = $toc->offeredCourse->course;
        $isLab = $course->lab == 1;
        $validTypes = ['Quiz', 'Assignment', 'LabTask'];

        foreach ($data as $type => $counts) {
            try {
                if (!in_array($type, $validTypes)) continue;

                $total = isset($counts['Total']) ? (int)$counts['Total'] : 0;
                $teacher = isset($counts['Teacher']) ? (int)$counts['Teacher'] : 0;
                $junior = array_key_exists('Junior Lecturer', $counts) ? $counts['Junior Lecturer'] : null;
                $dbTotal = task::where('teacher_offered_course_id', $tocId)
                               ->where('type', $type)
                               ->count();

                if ($total > $dbTotal) {
                    $errors[] = "$type: Total exceeds available tasks. Given=$total, Available=$dbTotal";
                    continue;
                }
                if ($isLab) {
                    if ($junior !== null) {
                        $jlCreatedCount = task::where('teacher_offered_course_id', $tocId)
                                              ->where('type', $type)
                                              ->where('CreatedBy', 'Junior Lecturer')
                                              ->count();
                                              $TeacherCreatedCount = task::where('teacher_offered_course_id', $tocId)
                                              ->where('type', $type)
                                              ->where('CreatedBy', 'Teacher')
                                              ->count();
                        if ($junior > $jlCreatedCount) {
                            $errors[] = "$type: Junior Lecturer count exceeded. Given=$junior, Available=$jlCreatedCount";
                            continue;
                        }
                        if ($teacher > $TeacherCreatedCount) {
                            $errors[] = "$type: Teacher count exceeded. Given=$teacher, Available=$TeacherCreatedCount";
                            continue;
                        }
                    }
                    task_consideration::updateOrCreate(
                        [
                            'teacher_offered_course_id' => $tocId,
                            'type' => $type,
                        ],
                        [
                            'top' => $total, // For lab, top = teacher
                            'jl_consider_count' => $junior !== null ? $junior : null,
                        ]
                    );
                } else {
                    // Non-lab: Check teacher only
                    $teacherCreatedCount = task::where('teacher_offered_course_id', $tocId)
                                               ->where('type', $type)
                                               ->where('CreatedBy', 'Teacher')
                                               ->count();

                    if ($teacher > $teacherCreatedCount) {
                        $errors[] = "$type: Teacher count exceeded. Given=$teacher, Available=$teacherCreatedCount";
                        continue;
                    }

                    task_consideration::updateOrCreate(
                        [
                            'teacher_offered_course_id' => $tocId,
                            'type' => $type,
                        ],
                        [
                            'top' => $total,
                            'jl_consider_count' => null, // Ignore Junior in non-lab
                        ]
                    );
                }
            } catch (\Exception $e) {
                $errors[] = "$type: Internal error - " . $e->getMessage();
            }
        }

        if (count($errors) > 0) {
            return response()->json(['errors' => $errors], 422);
        }

        return response()->json(['success' => 'Task considerations saved successfully.'],200);

    } catch (\Exception $e) {
        return response()->json(['error' => 'Unexpected error: ' . $e->getMessage()], 500);
    }
}
       public function updateTaskDetails(Request $request)
    {
        try {
            // Validate request data
            $request->validate([
                'task_id' => 'required|integer|exists:task,id',
                'points' => 'nullable|integer|min:0',
                'StartDateTime' => 'nullable|date',
                'EndDateTime' => 'nullable|date|after_or_equal:StartDateTime',
            ]);

            // Find the task
            $task = Task::findOrFail($request->task_id);

            // Update fields if provided
            if ($request->has('points')) {
                $task->points = $request->points;
            }
            if ($request->has('StartDateTime')) {
                $task->start_date = $request->StartDateTime;
            }
            if ($request->has('EndDateTime')) {
                $task->due_date = $request->EndDateTime;
            }

            // Save changes
            $task->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Task details updated successfully',
                'task' => $task
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function deleteTask(Request $request)
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|exists:task,id',
            ]);

            $taskId = $request->task_id;

            DB::beginTransaction();

            // Step 1: Delete related records from grader_task
            DB::table('grader_task')->where('Task_id', $taskId)->delete();

            // Step 2: Delete the task from the task table
            $deleted = DB::table('task')->where('id', $taskId)->delete();

            if ($deleted) {
                DB::commit(); // Commit transaction
                return response()->json([
                    'status' => 'success',
                    'message' => 'Task successfully deleted.',
                ], 200);
            } else {
                DB::rollBack(); // Rollback in case deletion fails
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task not found or already deleted.',
                ], 404);
            }
        } catch (Exception $e) {
            DB::rollBack(); // Rollback on exception
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateTaskEndDateTime(Request $request)
    {
        try {
            $validated = $request->validate([
                'task_id' => 'required|exists:task,id',
                'EndDateTime' => 'required|date_format:Y-m-d H:i:s|after:now',
            ]);

            $taskId = $request->task_id;
            $newEndDateTime = $request->EndDateTime;

            // Update the task
            $updated = DB::table('task')
                ->where('id', $taskId)
                ->update(['due_date' => $newEndDateTime]);

            if ($updated) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Task EndDateTime updated successfully.',
                ], 200);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Task not found or EndDateTime is the same.',
                ], 404);
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function workloadOfGrader(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $currentSessionId = (new Session())->getCurrentSessionId();
            $teacherGraders = teacher_grader::where('teacher_id', $teacher_id)->get();
            $activeGraders = [];
            foreach ($teacherGraders as $teacherGrader) {
                $grader = grader::find($teacherGrader->grader_id);
                if ($grader) {
                    $totalTasksAssigned = grader_task::where('Grader_id', $grader->id)->count();
                    $graderDetails = [
                        'teacher_grader_id' => $teacherGrader->id,
                        'grader_id' => $grader->id,
                        'RegNo' => $grader->student->RegNo,
                        'image' => $grader->student->image ? asset($grader->student->image) : null,
                        'name' => $grader->student->name,
                        'section' => (new section())->getNameByID($grader->student->section_id),
                        'status' => $grader->status,
                        'feedback' => $teacherGrader->feedback,
                        'type' => $grader->type,
                        'session' => (new session())->getSessionNameByID($teacherGrader->session_id),
                        'total_tasks_assigned' => $totalTasksAssigned, // ✅ Custom Attribute
                    ];

                    if ($teacherGrader->session_id == $currentSessionId) {
                        $activeGraders[] = $graderDetails;
                    }
                }
            }

            return response()->json([
                'status' => 'success',
                'active_graders' => $activeGraders,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getTeacherNotifications(Request $request)
    {
        try {
            $teacherId = $request->teacher_id;
            if (!$teacherId) {
                return response()->json(['status' => false, 'message' => 'teacher_id is required'], 400);
            }
            $teacher = Teacher::find($teacherId);
            if (!$teacher) {
                return response()->json(['status' => false, 'message' => 'Teacher not found'], 404);
            }
            $userId = $teacher->user_id;
            $broadcastNotifications = notification::where('Brodcast', true)
                ->where(function ($query) {
                    $query->where('reciever', 'Teacher')
                        ->orWhereNull('reciever');
                });
            $directNotifications = notification::where('TL_receiver_id', $userId);
            $notifications = $broadcastNotifications->get()->merge($directNotifications->get());
            $sortedNotifications = $notifications->sortByDesc('notification_date')->values();
            $finalResponse = $sortedNotifications->map(function ($note) {
                $senderName = 'System';
                $image = null;
                if ($note->sender === 'Admin') {
                    $Admin = admin::where('user_id', $note->TL_sender_id)->first();
                    if ($Admin) {
                        $senderName = $Admin->name ?? 'N/A';
                        $image = $Admin->image ? asset($Admin->image) : null;
                    }

                } else if ($note->sender === 'DataCell') {
                    $Datacell = datacell::where('user_id', $note->TL_sender_id)->first();
                    if ($Datacell) {
                        $senderName = $Datacell->name ?? 'N/A';
                        $image = $Datacell->image ? asset($Datacell->image) : null;
                    }
                }
                if (!empty($note->url)) {
                    if (str_contains($note->url, 'https://') || str_contains($note->url, 'http://')) {
                        $type = 'link';
                        $imageOrLink = $note->url;
                    } else {
                        $type = 'image';
                        $imageOrLink = asset($note->url);
                    }
                }

                return [
                    'id' => $note->id,
                    'title' => $note->title,
                    'description' => $note->description,
                    'url' => $note->url,
                    'notification_date' => $note->notification_date,
                    'sender' => $note->sender,
                    'sender_name' => $senderName,
                    'sender_image' => $image,
                    'media_type' => $type ?? null,
                    'media' => $imageOrLink ?? null

                ];
            });

            return response()->json(['status' => true, 'data' => $finalResponse], 200);

        } catch (Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function AddFeedback(Request $request)
    {
        try {
            $request->validate([
                'teacher_grader_id' => 'required',
                'feedback' => 'nullable|string|max:255' // Allow null to remove feedback
            ]);

            $teacherGrader = teacher_grader::find($request->teacher_grader_id);
            if (!$teacherGrader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher grader record not found.',
                ], 404);
            }

            // Update or remove feedback
            $teacherGrader->feedback = $request->feedback ?? null; // If feedback is null, remove it
            $teacherGrader->save();

            return response()->json([
                'success' => true,
                'message' => $request->feedback ? 'Feedback updated successfully.' : 'Feedback removed successfully.',
                'data' => $teacherGrader,
            ]);

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

    public function CopySemester(Request $request)
    {
        try {
            $validated = $request->validate([
                'course_id' => 'required',
                'source_session_id' => 'required',
                'destination_session_id' => 'required',
            ]);
            $courseName = $validated['course_id'];
            $sourceSessionName = $validated['source_session_id'];
            $destinationSessionName = $validated['destination_session_id'];
            $course = new Course();
            $course = course::find($courseName);
            if (!$course) {
                return response()->json(['error' => 'Course not found.'], 404);
            }
            $courseId = $course->id;
            $session = new Session();
            $sourceSessionId = $session->find($sourceSessionName)->id;
            $destinationSessionId = $session->find($destinationSessionName)->id;
            $destinationSessionName = $session->getSessionNameByID($destinationSessionId);
            $sourceSessionName = $session->getSessionIdByName($sourceSessionId);
            if (!$sourceSessionId || !$destinationSessionId) {
                return response()->json(['error' => 'Source or Destination session not found.'], 404);
            }
            $sourceOfferedCourse = offered_courses::where('course_id', $courseId)->where('session_id', $sourceSessionId)->first();
            $destinationOfferedCourse = offered_courses::where('course_id', $courseId)->where('session_id', $destinationSessionId)->first();

            if (!$sourceOfferedCourse || !$destinationOfferedCourse) {
                return response()->json(['error' => 'Course not offered in the source or destination session.'], 404);
            }
            $courseContents = coursecontent::where('offered_course_id', $sourceOfferedCourse->id)->get();
            $successfullyCopied = [];
            $errors = [];
            foreach ($courseContents as $content) {
                $newContentData = $content->toArray();
                unset($newContentData['id'], $newContentData['offered_course_id']);
                $newContentData['offered_course_id'] = $destinationOfferedCourse->id;
                $existingContent = coursecontent::where('title', $content->title)
                    ->where('type', $content->type)
                    ->where('week', $content->week)
                    ->where('offered_course_id', $destinationOfferedCourse->id)
                    ->first();

                if ($existingContent) {
                    $successfullyCopied[] = [
                        'status' => 'skipped',
                        'message' => "Content '{$content->title}' (Type: {$content->type}, Week: {$content->week}) already exists in the destination session."
                    ];
                    continue;
                }
                try {
                    $newContent = coursecontent::create($newContentData);
                    $newContentId = $newContent->id;
                    if ($content->type === 'Quiz' && $content->content === 'MCQS') {
                        $mcqs = quiz_questions::where('coursecontent_id', $content->id)->get();
                        foreach ($mcqs as $mcq) {
                            $newQuizQuestion = quiz_questions::create([
                                'question_no' => $mcq->question_no,
                                'question_text' => $mcq->question_text,
                                'points' => $mcq->points,
                                'coursecontent_id' => $newContentId,
                            ]);
                            foreach ($mcq->options as $option) {
                                \App\Models\options::create([
                                    'quiz_question_id' => $newQuizQuestion->id,
                                    'option_text' => $option->option_text,
                                    'is_correct' => $option->is_correct,
                                ]);
                            }

                        }
                        $successfullyCopied[] = [
                            'status' => 'success',
                            'message' => "Successfully copied content '{$content->title}' (ID: {$content->id})."
                        ];
                        continue;
                    }
                    if (in_array($content->type, ['Notes', 'Quiz', 'Assignment']) && $content->content) {
                        $directory = "{$destinationSessionName}/CourseContent/{$course->description}";
                        $newContentPath = FileHandler::copyFileToDestination($content->content, $content->title, $directory);
                        if (!$newContentPath) {
                            $errors[] = [
                                'status' => 'error',
                                'message' => "Failed to copy file for content ID {$content->id}."
                            ];
                            continue;
                        }
                        $newContent->content = $newContentPath;
                        $newContent->save();
                        if ($content->type === 'Notes') {
                            $existingTopics = coursecontent_topic::where('coursecontent_id', $content->id)->get();
                            foreach ($existingTopics as $topic) {
                                $data = [
                                    'coursecontent_id' => $newContent->id,
                                    'topic_id' => $topic->topic_id,
                                ];
                                $updatedTopic = coursecontent_topic::updateOrCreate(
                                    [
                                        'coursecontent_id' => $newContent->id,
                                        'topic_id' => $topic->topic_id
                                    ]
                                );
                            }
                        }
                    }
                    $successfullyCopied[] = [
                        'status' => 'success',
                        'message' => "Successfully copied content '{$content->title}' (ID: {$content->id})."
                    ];
                } catch (Exception $e) {
                    $errors[] = [
                        'status' => 'error',
                        'message' => "Failed to copy content ID {$content->id}: {$e->getMessage()}."
                    ];
                }
            }
            return response()->json([
                'message' => 'Course content duplication completed.',
                'success' => $successfullyCopied,
                'errors' => $errors,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function updateCourseContentTopicStatus(Request $request): mixed
    {
        try {
            $validated = $request->validate([
                'teacher_offered_courses_id' => 'required|integer',
                'topic_id' => 'required|integer',
                'coursecontent_id' => 'required|integer',
                'status' => 'required|boolean',
            ]);
            $teacherOfferedCourseId = $validated['teacher_offered_courses_id'];
            $topicId = $validated['topic_id'];
            $courseContentId = $validated['coursecontent_id'];
            $status = $validated['status'];
            $courseContentTopic = coursecontent_topic::where('coursecontent_id', $courseContentId)
                ->where('topic_id', $topicId)->first();
            if (!$courseContentTopic) {
                throw new Exception('The Topic is Not the part of Course Contnet');
            }
            $existingRecord = DB::table('t_coursecontent_topic_status')
                ->where('teacher_offered_courses_id', $teacherOfferedCourseId)
                ->where('coursecontent_id', $courseContentId)
                ->where('topic_id', $topicId)
                ->first();

            if ($existingRecord) {
                DB::table('t_coursecontent_topic_status')
                    ->where('teacher_offered_courses_id', $teacherOfferedCourseId)
                    ->where('coursecontent_id', $courseContentId)
                    ->where('topic_id', $topicId)
                    ->update(['Status' => $status]);
            } else {
                DB::table('t_coursecontent_topic_status')->insert([
                    'teacher_offered_courses_id' => $teacherOfferedCourseId,
                    'coursecontent_id' => $courseContentId,
                    'topic_id' => $topicId,
                    'Status' => $status,
                ]);
            }
            return response()->json([
                'message' => 'Course content topic status updated successfully.',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function LoadFile(Request $request)
    {
        $validated = $request->validate([
            'path' => 'required|string',
        ]);
        $path = $validated['path'];
        return asset($path);
        // $fileData = FileHandler::getFileByPath($path);
        // if ($fileData === null) {
        //     return response()->json([
        //         'error' => 'File not found or unable to load the file.',
        //     ], 404);
        // }
        // return response()->json([
        //     'success' => true,
        //     'file_data' => $fileData,
        // ], 200);
    }
    public static function getStudentIdsByTeacherOfferedCourseId($teacherOfferedCourseId)
    {
        try {
            $teacherOfferedCourse = teacher_offered_courses::find($teacherOfferedCourseId);
            if (!$teacherOfferedCourse) {
                return null;
            }
            $offeredCourseId = $teacherOfferedCourse->offered_course_id;
            $sectionId = $teacherOfferedCourse->section_id;
            $studentIds = student_offered_courses::where('offered_course_id', $offeredCourseId)
                ->where('section_id', $sectionId)
                ->pluck('student_id')
                ->toArray();

            return $studentIds;
        } catch (Exception $e) {
            return null;
        }
    }
    public function getSectionList(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
        ]);
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])->find($teacherOfferedCourseId);

        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }
        $offeredCourseId = $teacherOfferedCourse->offered_course_id;
        $sectionId = $teacherOfferedCourse->section_id;
        $studentCourses = student_offered_courses::where('offered_course_id', $offeredCourseId)
            ->where('section_id', $sectionId)
            ->with(['student'])
            ->get();

        if ($studentCourses->isEmpty()) {
            return response()->json([
                'error' => 'No students found for the given teacher offered course.',
            ], 404);
        }
        $studentsData = $studentCourses->map(function ($studentCourse) {
            $student = $studentCourse->student;
            if (!$student) {
                return null;
            }

            return [
                "id" => $student->id,
                "name" => $student->name,
                "RegNo" => $student->RegNo,
                "CGPA" => $student->cgpa,
                "Gender" => $student->gender,
                "Guardian" => $student->guardian,
                "username" => $student->user->username,
                "email" => $student->user->email,
                "InTake" => (new session())->getSessionNameByID($student->session_id),
                "Program" => $student->program->name,
                "Section" => (new section())->getNameByID($student->section_id),
                "Image" => FileHandler::getFileByPath($student->image) ?? null,
            ];
        })->filter();
        return response()->json([
            'success' => true,
            'Session Name' => ($teacherOfferedCourse->offeredCourse->session->name . '-' . $teacherOfferedCourse->offeredCourse->session->year) ?? null,
            'Course Name' => $teacherOfferedCourse->offeredCourse->course->name,
            'Section Name' => (new section())->getNameByID($teacherOfferedCourse->section->id),
            'students_count' => $studentCourses->count(),
            'students' => $studentsData,
        ], 200);
    }
    public function getSectionTaskResult(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required',
        ]);
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];

        try {
            $teacherOfferedCourse = teacher_offered_courses::find($teacherOfferedCourseId);
            if (!$teacherOfferedCourse) {
                return response()->json(['error' => 'Teacher offered course not found'], 404);
            }

            $studentIds = $this->getStudentIdsByTeacherOfferedCourseId($teacherOfferedCourseId);
            if (!$studentIds) {
                return response()->json(['error' => 'No students found for the given teacher_offered_course_id'], 404);
            }
            $result = [];
            $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->where('isMarked', 1)
                ->get();
            $totalAssignments = $tasks->where('type', 'Assignment')->count();
            $totalQuizzes = $tasks->where('type', 'Quiz')->count();
            $totalLabTasks = $tasks->where('type', 'LabTask')->count();
            foreach ($studentIds as $studentId) {
                $student = student::find($studentId);
                if (!$student) {
                    continue;
                }

                $groupedTasks = [
                    'Quiz' => ['total_marks' => 0, 'obtained_marks' => 0, 'percentage' => 0, 'tasks' => [], 'obtained_display' => ''],
                    'Assignment' => ['total_marks' => 0, 'obtained_marks' => 0, 'percentage' => 0, 'tasks' => [], 'obtained_display' => ''],
                    'LabTask' => ['total_marks' => 0, 'obtained_marks' => 0, 'percentage' => 0, 'tasks' => [], 'obtained_display' => ''],
                ];

                foreach ($tasks as $task) {
                    $studentTaskResult = student_task_result::where('Task_id', $task->id)
                        ->where('Student_id', $studentId)
                        ->first();

                    $obtainedMarks = $studentTaskResult ? $studentTaskResult->ObtainedMarks : 0;
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
                foreach ($groupedTasks as $type => $group) {
                    $totalMarks = $group['total_marks'];
                    $obtainedMarks = $group['obtained_marks'];
                    $groupedTasks[$type]['percentage'] = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
                    $groupedTasks[$type]['obtained_display'] = $obtainedMarks;
                    $groupedTasks[$type]['percentage'] .= '%';
                }
                $overallObtained = $groupedTasks['LabTask']['obtained_display'] +
                    $groupedTasks['Assignment']['obtained_display'] +
                    $groupedTasks['Quiz']['obtained_display'];

                $overallTotal = $groupedTasks['LabTask']['total_marks'] +
                    $groupedTasks['Assignment']['total_marks'] +
                    $groupedTasks['Quiz']['total_marks'];

                $overallPercentage = $overallTotal > 0 ? round(($overallObtained / $overallTotal) * 100, 2) : 0;
                $studentInfo = [
                    'Student_Id' => $student->id,
                    'Student_name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'Lab_Task_Percentage' => $groupedTasks['LabTask']['percentage'],
                    'Lab_Task_Obtained Marks' => $groupedTasks['LabTask']['obtained_display'],
                    'Lab_Task_Total Marks' => $groupedTasks['LabTask']['total_marks'],
                    'Assignment_Task_Percentage' => $groupedTasks['Assignment']['percentage'],
                    'Assignment_Task_Obtained Marks' => $groupedTasks['Assignment']['obtained_display'],
                    'Assignment_Task_Total Marks' => $groupedTasks['Assignment']['total_marks'],
                    'Quiz_Task_Percentage' => $groupedTasks['Quiz']['percentage'],
                    'Quiz_Task_Obtained Marks' => $groupedTasks['Quiz']['obtained_display'],
                    'Quiz_Task_Total Marks' => $groupedTasks['Quiz']['total_marks'],
                    'Overall_Obtained_Marks' => $overallObtained,
                    'Overall_Total_Marks' => $overallTotal,
                    'Overall_Percentage' => $overallPercentage
                ];
                $result[] = $studentInfo;
            }
            $response = [
                'Total_Assignments_Conducted' => $totalAssignments,
                'Total_Quizzes_Conducted' => $totalQuizzes,
                'Total_LabTasks_Conducted' => $totalLabTasks,
                'Results' => $result
            ];

            return response()->json($response, 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
        }
    }
    public function getSingleStudentTaskResult(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'student_id' => 'required|integer',
        ]);

        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])->find($teacherOfferedCourseId);
        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }
        $studentId = $validated['student_id'];
        try {
            $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->where('isMarked', 1)
                ->get();
            if ($tasks->isEmpty()) {
                return response()->json(['error' => 'No tasks found for the given teacher_offered_course_id'], 404);
            }
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
            foreach ($tasks as $task) {
                $studentTaskResult = student_task_result::where('Task_id', $task->id)
                    ->where('Student_id', $studentId)
                    ->first();
                $obtainedMarks = $studentTaskResult ? $studentTaskResult->ObtainedMarks : 0;
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
                    $juniorLecturer = juniorlecturer::where('id', $task->teacher_id)->first();
                    $taskDetails['Given By'] = 'Junior Lecturer';
                    $taskDetails['creator_name'] = $juniorLecturer->name ?? 'Unknown';
                }
                $offeredCourse = offered_courses::where('id', $teacherOfferedCourse->offeredCourse->id)->first();
                $course = $offeredCourse ? Course::where('id', $offeredCourse->course_id)->first() : null;
                $taskDetails['course_name'] = $course->name ?? 'Unknown';
                $groupedTasks[$task->type]['tasks'][] = $taskDetails;
                $groupedTasks[$task->type]['total_marks'] += $task->points;
                $groupedTasks[$task->type]['obtained_marks'] += $obtainedMarks;
            }
            foreach ($groupedTasks as $type => $group) {
                $totalMarks = $group['total_marks'];
                $obtainedMarks = $group['obtained_marks'];
                $groupedTasks[$type]['percentage'] = $totalMarks > 0 ? round(($obtainedMarks / $totalMarks) * 100, 2) : 0;
            }
            $totalTasks = [
                'Total_Conducted_Quiz' => $tasks->where('type', 'Quiz')->count(),
                'Total_Conducted_Assignment' => $tasks->where('type', 'Assignment')->count(),
                'Total_Conducted_LabTask' => $tasks->where('type', 'LabTask')->count(),
            ];

            return response()->json([
                'total_conducted_tasks' => $totalTasks,
                'tasks' => $groupedTasks,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
        }
    }
    public function getAttendanceBySubjectForAllStudents(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
        ]);
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        try {
            $studentIds = self::getStudentIdsByTeacherOfferedCourseId($teacherOfferedCourseId);
            if (!$studentIds) {
                return response()->json(['error' => 'No students found for the given teacher_offered_course_id'], 404);
            }
            $result = [];
            $totalConductedClasses = attendance::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->distinct('date_time')
                ->count('date_time');
            $totalConductedLab = attendance::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->distinct('date_time')->where('isLab', 1)
                ->count('date_time');
            $totalConductedLectures = attendance::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->distinct('date_time')->where('isLab', 0)
                ->count('date_time');
            foreach ($studentIds as $studentId) {
                $attendanceData = attendance::getAttendanceBySubject($teacherOfferedCourseId, $studentId);
                $studentInfo = [
                    'Student_Id' => $studentId,
                    'Student_Image' => student::find($studentId)->image ? asset(student::find($studentId)->image) : null,
                    'Student_Name' => student::find($studentId)->name ?? 'Unknown',
                    'RegNo' => student::find($studentId)->RegNo ?? 'Unknown',
                    'Lecture_Attended' => $attendanceData['Class']['total_present'] ?? 0,
                    'Lecture_Percentage' => $attendanceData['Class']['percentage'] ?? 0,
                    'Lab_Attended' => $attendanceData['Lab']['total_present'] ?? 0,
                    'Lab_Percentage' => $attendanceData['Lab']['percentage'] ?? 0,
                    'Total_Attended_Lectures' => $attendanceData['Total']['total_present'] ?? 0,
                    'Total_Percentage' => $attendanceData['Total']['percentage'],
                ];
                $result[] = $studentInfo;
            }

            return response()->json([
                'total_conducted' => $totalConductedClasses,
                'lab_conducted' => $totalConductedLab,
                'classes_conducted' => $totalConductedLectures,
                'students' => $result
            ], 200);

        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
        }
    }
    public function FullTimetable(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $timetable = timetable::getFullTimetableByTeacherId($teacher_id);
            return response()->json([
                'status' => 'success',
                'data' => $timetable
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
    public function getAllVenues()
    {
        $venues = venue::select('id', 'venue')->get();
        if ($venues->isEmpty()) {
            return response()->json(['error' => 'No venues found'], 404);
        }

        return response()->json($venues);
    }
    public function TodayClass(Request $request)
    {
        try {
            $teacherId = $request->teacher_id;
            $todayDay = Carbon::now()->format('l');
            if (excluded_days::checkHoliday()) {
                return response()->json(['message' => excluded_days::checkHolidayReason()], 404);
            }
            if (excluded_days::checkReschedule()) {
                $todayDay = excluded_days::checkRescheduleDay();
            }
            $currentSession = (new session())->getCurrentSessionId();
            if ($currentSession == 0) {
                return response()->json(['error' => 'No current session found'], 404);
            }
            $timetable = timetable::with([
                'course:name,id,description',
                'teacher:name,id',
                'venue:venue,id',
                'dayslot:day,start_time,end_time,id',
                'juniorLecturer:name',
                'section'
            ])->whereHas('dayslot', function ($query) use ($todayDay) {
                $query->where('day', $todayDay);
            })
                ->where('teacher_id', $teacherId)
                ->where('session_id', (new session())->getCurrentSessionId())
                ->get()
                ->map(function ($item) {
                    return [
                        // 'day' => $item->dayslot->day,
                        'coursename' => $item->course->name,
                        'description' => $item->course->description,
                        'course_id' => $item->course->id,
                        'section_id' => $item->section->id,
                        'teachername' => $item->teacher->name ?? 'N/A',
                        'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
                        'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                        'venue' => $item->venue->venue,
                        'venue_id' => $item->venue->id,
                        'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('H:i') : null,
                        'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('H:i') : null,
                    ];
                });
            $filteredTimetable = $timetable->map(function ($item) {
                $sectionid = $item['section_id'];
                $courseid = $item['course_id'];
                $offeredCourseId = offered_courses::where('course_id', $courseid)
                    ->where('session_id', (new session())->getCurrentSessionId())
                    ->first();
                if (!$offeredCourseId) {
                    return null; // Skip if no offered_course found
                }

                $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionid)
                    ->where('offered_course_id', $offeredCourseId->id)
                    ->first();

                if (!$teacherOfferedCourse) {
                    return null; // Skip if no teacher_offered_course found
                }

                $formattedDateTime = $item['start_time'];
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i', Carbon::now()->toDateString() . ' ' . $formattedDateTime)->format('Y-m-d H:i:s');

                $attendanceMarked = Attendance::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                    ->where('date_time', $startDateTime)
                    ->exists();

                $status = $attendanceMarked ? 'Marked' : 'Unmarked';

                return [
                    "FormattedData" => $item['description'] . '_( ' . $item['section'] . ' )_' . $item['venue'] . '_(' . $item['start_time'] . '-' . $item['end_time'] . ')',
                    "teacher_offered_course_id" => $teacherOfferedCourse->id,
                    "attendance_status" => $status,
                    "fixed_date" => $startDateTime,
                    'coursename' => $item['coursename'],
                    'description' => $item['description'],
                    'course_id' => $item['course_id'],
                    'section_id' => $item['section_id'],
                    'teachername' => $item['teachername'],
                    'juniorlecturername' => $item['juniorlecturername'],
                    'section' => $item['section'],
                    'venue' => $item['venue'],
                    'venue_id' => $item['venue_id'],
                    'start_time' => $item['start_time'],
                    'end_time' => $item['end_time'],

                ];
            })->filter(); // To remove any nulls if courses or teacher_offered_courses are missing

            return response()->json($filteredTimetable->values());


        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
        }
    }
    // public function TodayClass(Request $request)
    // {
    //     try {
    //         $teacherId = $request->teacher_id;
    //         $todayDay = Carbon::now()->format('l');
    //         if (excluded_days::checkHoliday()) {
    //             return response()->json(['message' => excluded_days::checkHolidayReason()], 404);
    //         }
    //         if (excluded_days::checkReschedule()) {
    //             $todayDay = excluded_days::checkRescheduleDay();
    //         }
    //         $currentSession = (new session())->getCurrentSessionId();
    //         if ($currentSession == 0) {
    //             return response()->json(['error' => 'No current session found'], 404);
    //         }
    //         $timetable = timetable::with([
    //             'course:name,id,description',
    //             'teacher:name,id',
    //             'venue:venue,id',
    //             'dayslot:day,start_time,end_time,id',
    //             'juniorLecturer:name',
    //             'section'
    //         ])->whereHas('dayslot', function ($query) use ($todayDay) {
    //             $query->where('day', $todayDay);
    //         })
    //             ->where('teacher_id', $teacherId)
    //             ->where('session_id', (new session())->getCurrentSessionId())
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'day' => $item->dayslot->day,
    //                     'coursename' => $item->course->name,
    //                     'description' => $item->course->description,
    //                     'course_id' => $item->course->id,
    //                     'section_id' => $item->section->id,
    //                     'teachername' => $item->teacher->name ?? 'N/A',
    //                     'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
    //                     'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
    //                     'venue' => $item->venue->venue,
    //                     'venue_id' => $item->venue->id,
    //                     'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('H:i') : null,
    //                     'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('H:i') : null,
    //                 ];
    //             });
    //         $filteredTimetable = $timetable->map(function ($item) {
    //             $sectionid = $item['section_id'];
    //             $courseid = $item['course_id'];
    //             $offeredCourseId = offered_courses::where('course_id', $courseid)
    //                 ->where('session_id', (new session())->getCurrentSessionId())
    //                 ->first();
    //             if (!$offeredCourseId) {
    //                 return null; // Skip if no offered_course found
    //             }

    //             $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionid)
    //                 ->where('offered_course_id', $offeredCourseId->id)
    //                 ->first();

    //             if (!$teacherOfferedCourse) {
    //                 return null; // Skip if no teacher_offered_course found
    //             }

    //             $formattedDateTime = $item['start_time'];
    //             $startDateTime = Carbon::createFromFormat('Y-m-d H:i', Carbon::now()->toDateString() . ' ' . $formattedDateTime)->format('Y-m-d H:i:s');

    //             $attendanceMarked = Attendance::where('teacher_offered_course_id', $teacherOfferedCourse->id)
    //                 ->where('date_time', $startDateTime)
    //                 ->exists();

    //             $status = $attendanceMarked ? 'Marked' : 'Unmarked';

    //             return [
    //                 "FormattedData" => $item['description'] . '_( ' . $item['section'] . ' )_' . $item['venue'] . '_(' . $item['start_time'] . '-' . $item['end_time'] . ')',
    //                 "teacher_offered_course_id" => $teacherOfferedCourse->id,
    //                 "attendance_status" => $status,
    //                 "fixed_date" => $startDateTime,
    //                 'coursename' => $item['coursename'],
    //                 'day' => $item['day'],
    //                 'description' => $item['description'],
    //                 'course_id' => $item['course_id'],
    //                 'section_id' => $item['section_id'],
    //                 'teachername' => $item['teachername'],
    //                 'juniorlecturername' => $item['juniorlecturername'],
    //                 'section' => $item['section'],
    //                 'venue' => $item['venue'],
    //                 'venue_id' => $item['venue_id'],
    //                 'start_time' => $item['start_time'],
    //                 'end_time' => $item['end_time'],

    //             ];
    //         })->filter(); // To remove any nulls if courses or teacher_offered_courses are missing

    //         return response()->json($filteredTimetable->values());


    //     } catch (Exception $e) {
    //         return response()->json(['error' => 'An unexpected error occurred', 'message' => $e->getMessage()], 500);
    //     }
    // }
    public function getTodayClassesWithAttendanceStatus($teacherId)
    {
        $currentDay = Carbon::now()->format('l');

        // Check holidays or reschedule
        if (excluded_days::checkHoliday()) {
            return response()->json(['message' => excluded_days::checkHolidayReason()], 404);
        }
        if (excluded_days::checkReschedule()) {
            $currentDay = excluded_days::checkRescheduleDay();
        }

        $currentDate = Carbon::now()->toDateString();
        $currentSessionId = (new session())->getCurrentSessionId();

        // Fetch today's classes directly using Eloquent with relationships
        $classes = Timetable::with(['venue', 'course', 'section.program', 'dayslot'])
            ->where('teacher_id', $teacherId)
            ->whereHas('dayslot', function ($q) use ($currentDay) {
                $q->where('day', $currentDay);
            })
            ->get();

        if ($classes->isEmpty()) {
            return response()->json(['message' => 'No classes found for today'], 404);
        }

        // Pre-fetch all relevant offered_courses for performance
        $offeredCourses = offered_courses::whereIn('course_id', $classes->pluck('course_id'))
            ->where('session_id', $currentSessionId)
            ->get()
            ->keyBy('course_id');

        // Pre-fetch teacher_offered_courses
        $teacherOfferedCourses = teacher_offered_courses::where('teacher_id', $teacherId)
            ->whereIn('section_id', $classes->pluck('section_id'))
            ->whereIn('offered_course_id', $offeredCourses->pluck('id'))
            ->get()
            ->keyBy(function ($item) {
                return $item->section_id . '-' . $item->offered_course_id;
            });

        // Map and prepare response
        $formattedClasses = $classes->map(function ($class) use ($teacherOfferedCourses, $offeredCourses, $currentDate) {
            $offeredCourse = $offeredCourses[$class->course_id] ?? null;

            if (!$offeredCourse) {
                $class->attendance_status = 'Unmarked';
                $class->teacher_offered_course_id = null;
                return $this->formatClassTime($class);
            }

            $teacherCourseKey = $class->section_id . '-' . $offeredCourse->id;
            $teacherOfferedCourse = $teacherOfferedCourses[$teacherCourseKey] ?? null;
            $class->teacher_offered_course_id = $teacherOfferedCourse->id ?? null;

            $attendanceMarked = false;
            if ($teacherOfferedCourse) {
                $startDateTime = Carbon::parse($currentDate . ' ' . $class->dayslot->start_time)->toDateTimeString();
                $attendanceMarked = Attendance::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                    ->where('date_time', $startDateTime)
                    ->exists();
            }

            $class->attendance_status = $attendanceMarked ? 'Marked' : 'Unmarked';

            return $this->formatClassTime($class);
        });

        return response()->json($formattedClasses);
    }

    private function formatClassTime($class)
    {
        try {
            $class->start_time = Carbon::parse($class->dayslot->start_time)->format('g:i A');
            $class->end_time = Carbon::parse($class->dayslot->end_time)->format('g:i A');
        } catch (Exception $e) {
            $class->start_time = 'Invalid Time';
            $class->end_time = 'Invalid Time';
        }

        $class->section = $class->section->program->name . '-' . $class->section->semester . $class->section->group;
        $class->venue_name = $class->venue->venue;
        $class->course_name = $class->course->name;
        $class->day_slot = $class->dayslot->day;

        unset($class->dayslot);
        unset($class->venue);
        unset($class->course);
        unset($class->section->program);

        return $class;
    }

    // public function getTodayClassesWithAttendanceStatus($teacherId)
    // {
    //     $currentDay = Carbon::now()->format('l');
    //     if (excluded_days::checkHoliday()) {
    //         return response()->json(['message' => excluded_days::checkHolidayReason()], 404);
    //     }
    //     if (excluded_days::checkReschedule()) {
    //         $currentDay = excluded_days::checkRescheduleDay();
    //     }
    //     $currentDate = Carbon::now()->toDateString();
    //     $classes = Timetable::join('venue', 'timetable.venue_id', '=', 'venue.id')
    //         ->join('course', 'timetable.course_id', '=', 'course.id')
    //         ->join('session', 'timetable.session_id', '=', 'session.id')
    //         ->join('section', 'timetable.section_id', '=', 'section.id')
    //         ->join('program', 'section.program', '=', 'program.name') // Join with the program table
    //         ->join('dayslot', 'timetable.dayslot_id', '=', 'dayslot.id')
    //         ->join('teacher', 'timetable.teacher_id', '=', 'teacher.id')
    //         ->where('timetable.teacher_id', $teacherId)
    //         ->where('dayslot.day', $currentDay)
    //         ->select(
    //             'timetable.id AS timetable_id',
    //             'timetable.section_id',
    //             'timetable.course_id AS offered_course_id',
    //             'venue.venue AS venue_name',
    //             'venue.id AS venue_id',
    //             'course.name AS course_name',
    //             DB::raw("CONCAT(program.name, '-', section.semester, section.group) AS section"),
    //             'dayslot.day AS day_slot',
    //             'dayslot.start_time',
    //             'dayslot.end_time',
    //             'timetable.type AS class_type'
    //         )
    //         ->get();

    //     if ($classes->isEmpty()) {
    //         return response()->json(['message' => 'No classes found for today'], 404);
    //     }

    //     $formattedClasses = $classes->map(function ($class) use ($teacherId, $currentDate) {
    //         $offered_course_data = offered_courses::where('course_id', $class->offered_course_id)
    //             ->where('session_id', (new session())->getCurrentSessionId())
    //             ->first();

    //         if (!$offered_course_data) {
    //             $class->attendance_status = 'Unmarked';
    //             $class->teacher_offered_course_id = null;
    //             return $class;
    //         }
    //         try {
    //             $startDateTime = Carbon::parse($currentDate . ' ' . $class->start_time)->toDateTimeString();
    //         } catch (Exception $e) {
    //             $class->attendance_status = 'Unmarked';
    //             $class->teacher_offered_course_id = null;
    //             return $class;
    //         }
    //         $teacherOfferedCourse = teacher_offered_courses::where('teacher_id', $teacherId)
    //             ->where('section_id', $class->section_id)
    //             ->where('offered_course_id', $offered_course_data->id)
    //             ->first();

    //         $class->teacher_offered_course_id = $teacherOfferedCourse->id ?? null;
    //         $attendanceMarked = false;
    //         if ($teacherOfferedCourse) {
    //             $attendanceMarked = Attendance::where('teacher_offered_course_id', $teacherOfferedCourse->id)
    //                 ->where('date_time', $startDateTime)
    //                 ->exists();
    //         }
    //         try {
    //             $class->start_time = Carbon::parse($class->start_time)->format('g:i A');
    //             $class->end_time = Carbon::parse($class->end_time)->format('g:i A');
    //         } catch (Exception $e) {
    //             $class->start_time = 'Invalid Time';
    //             $class->end_time = 'Invalid Time';
    //         }

    //         $class->attendance_status = $attendanceMarked ? 'Marked' : 'Unmarked';

    //         return $class;
    //     });

    //     return response()->json($formattedClasses);
    // }
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
                    'Image' => student::find($item->attendance->student_id)->image ? asset(student::find($item->attendance->student_id)->image) : null,
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
            $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->whereIn('teacher_offered_course_id', $teacherOfferedCourseIds)
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
                if ($task->isMarked || ($dueDate < $currentDate && $task->courseContent->content == 'MCQS')) {
                    $completedTasks[] = [
                        'task_id' => $task->id,
                        'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                        'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                        'title' => $task->title,
                        'type' => ($task->courseContent->content == 'MCQS') ? 'MCQS' : $task->type,
                        ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                            ? Action::getMCQS($task->courseContent->id)
                            : asset($task->courseContent->content),
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        'marking_status' => $task->isMarked ? 'Marked' : 'Un-Marked',
                        'marking_info' => $markingInfo ?? null,
                        'Is Allocated To Grader' => $Assigned,
                        'Grader Info For this Task' => $message
                    ];
                } else if ($startDate > $currentDate) {
                    $upcomingTasks[] = [
                        'task_id' => $task->id,
                        'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                        'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                        'title' => $task->title,
                        'type' => ($task->courseContent->content == 'MCQS') ? 'MCQS' : $task->type,
                        ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                            ? Action::getMCQS($task->courseContent->id)
                            : asset($task->courseContent->content),
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        'marking_info' => $markingInfo ?? 'Not-Marked',
                        'Is Allocated To Grader' => $Assigned,
                        'Grader Info For this Task' => $message
                    ];
                    ;
                } else if ($startDate <= $currentDate && $dueDate >= $currentDate) {
                    $ongoingTasks[] = [
                        'task_id' => $task->id,
                        'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                        'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                        'title' => $task->title,
                        'type' => ($task->courseContent->content == 'MCQS') ? 'MCQS' : $task->type,
                        ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                            ? Action::getMCQS($task->courseContent->id)
                            : asset($task->courseContent->content),
                        'created_by' => $task->CreatedBy,
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        'marking_status' => $task->isMarked ? 'Marked' : 'Un-Marked',
                        'Is Allocated To Grader' => $Assigned,
                        'Grader Info For this Task' => $message
                    ];
                } else if ($dueDate < $currentDate && !$task->isMarked) {
                    $unMarkedTasks[] = [
                        'task_id' => $task->id,
                        'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                        'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                        'title' => $task->title,
                        'type' => ($task->courseContent->content == 'MCQS') ? 'MCQS' : $task->type,
                        ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                            ? Action::getMCQS($task->courseContent->id)
                            : asset($task->courseContent->content),
                        'points' => $task->points,
                        'start_date' => $task->start_date,
                        'due_date' => $task->due_date,
                        'marking_status' => $task->isMarked ? 'Marked' : 'Un-Marked',
                        'Is Allocated To Grader' => $Assigned,
                        'Grader Info For this Task' => $message
                    ];
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
            $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])
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
                'task_id' => 'required|exists:task,id',
                'grader_id' => 'required|exists:grader,id',
            ]);

            // Check if task is already assigned
            $existingAssignment = grader_task::where('Task_id', $request->task_id)->first();

            if ($existingAssignment) {
                // ✅ Correct way to update without 'id'
                grader_task::where('Task_id', $request->task_id)
                    ->update(['Grader_id' => $request->grader_id]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Task assignment updated successfully.',
                    'data' => [
                        'task_id' => $request->task_id,
                        'grader_id' => $request->grader_id,
                    ],
                ], 200);
            }

            // If task is not assigned, create a new entry
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
                        'teacher_grader_id' => $teacherGrader->id,
                        'grader_id' => $grader->id,
                        'RegNo' => $grader->student->RegNo,
                        'image' => $grader->student->image ? asset($grader->student->image) : null,
                        'name' => $grader->student->name,
                        'section' => (new section())->getNameByID($grader->student->section_id),
                        'status' => $grader->status,
                        'feedback' => $teacherGrader->feedback,
                        'type' => $grader->type,
                        'session' => (new session())->getSessionNameByID($teacherGrader->session_id),
                    ];
                    if ($teacherGrader->session_id == $currentSessionId) {
                        $activeGraders[] = $graderDetails;
                    } else {
                        $previousGraders[] = $graderDetails;  // In previous sessions
                    }
                }
            }
            $previousGraders = collect($previousGraders)->groupBy('session');
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

                $taskIds = task::where('teacher_offered_course_id', $singleSection['teacher_offered_course_id'])
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
                                : asset($task->content),
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

            $course_content = coursecontent::find($coursecontent_id);
            if (!$course_content) {
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

                if (!$section || $section == 0) {
                    $insertedTasks[] = [
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
                    $insertedTasks[] = [
                        'status' => 'error',
                        'message' => "Teacher-offered course not found for section '{$sectionName}' and course '{$courseName}'.",
                    ];
                    continue;
                }

                $taskNo = Action::getTaskCount($teacherOfferedCourse->id, $type);
                if ($taskNo > 0 && $taskNo < 10) {
                    $taskNo = "0" . $taskNo;
                }

                $filename = $course->description . '-' . $type . $taskNo . '-' . $sectionName;
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

                $task = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('coursecontent_id', $coursecontent_id)->first();

                if ($task) {
                    $task->update($taskData);
                    $insertedTasks[] = ['status' => 'Task is Already Allocated ! Just Updated the informations', 'task' => $task];
                } else {
                    $task = task::create($taskData);
                    $insertedTasks[] = ['status' => 'Task Allocated Successfully', 'task' => $task];
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
    public function storeOrUpdateTaskConsiderations(Request $request)
    {
        $responses = [];
        try {
            $validatedData = $request->validate([
                'teacher_offered_course_id' => 'required|integer|exists:teacher_offered_courses,id',
                'records' => 'required|array|min:1',
                'records.*.type' => 'required|string|in:Quiz,Assignment,LabTask',
                'records.*.top' => 'required|integer|min:0',
                'records.*.jl_task_count' => 'nullable|integer|min:0',
            ]);
            $teacherOfferedCourseId = $validatedData['teacher_offered_course_id'];
            $teacherOfferedCourse = teacher_offered_courses::with('offeredCourse.course')->find($teacherOfferedCourseId);
            if (!$teacherOfferedCourse) {
                throw new Exception('No record found in teacher allocation!');
            }
            $course = $teacherOfferedCourse->offeredCourse->course;
            foreach ($validatedData['records'] as $record) {
                try {
                    if ($course->lab) {
                        $jlTaskCount = $record['jl_task_count'] ?? 0;
                        $taskCount = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                            ->where('type', $record['type'])
                            ->count();
                        if ($record['top'] > $taskCount) {
                            $responses[] = [
                                'status' => 'error',
                                'teacher_offered_course_id' => $teacherOfferedCourseId,
                                'type' => $record['type'],
                                'message' => "You have conducted $taskCount {$record['type']} tasks. You cannot consider more tasks than conducted.",
                            ];
                            continue;
                        }
                        $teacherTaskCount = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                            ->where('type', $record['type'])
                            ->where('CreatedBy', 'Teacher')
                            ->count();
                        $jlTaskCountFromDb = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                            ->where('type', $record['type'])
                            ->where('CreatedBy', 'JuniorLecturer')
                            ->count();
                        $teacherCount = $record['top'] - $jlTaskCount;
                        $jlCount = $jlTaskCount;
                        if ($teacherTaskCount < $teacherCount || $jlTaskCountFromDb < $jlCount) {
                            $responses[] = [
                                'status' => 'error',
                                'teacher_offered_course_id' => $teacherOfferedCourseId,
                                'type' => $record['type'],
                                'message' => "Task count exceeds limits. Teacher tasks allowed: $teacherCount, Junior Lecturer tasks allowed: $jlCount.",
                            ];
                            continue;
                        }

                        $taskConsideration = task_consideration::updateOrCreate(
                            [
                                'teacher_offered_course_id' => $teacherOfferedCourse->id,
                                'type' => $record['type']
                            ],
                            [
                                'top' => $record['top'],
                                'jl_consider_count' => $jlTaskCount,
                            ]
                        );
                    } else {
                        $taskCount = task::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                            ->where('type', $record['type'])
                            ->count();
                        if ($record['top'] > $taskCount) {
                            $responses[] = [
                                'status' => 'error',
                                'teacher_offered_course_id' => $teacherOfferedCourseId,
                                'type' => $record['type'],
                                'message' => "You have conducted $taskCount {$record['type']} tasks. You cannot consider more tasks than conducted.",
                            ];
                            continue;
                        }

                        $taskConsideration = task_consideration::updateOrCreate(
                            [
                                'teacher_offered_course_id' => $teacherOfferedCourse->id,
                                'type' => $record['type']
                            ],
                            [
                                'top' => $record['top'],
                                'jl_consider_count' => 0,
                            ]
                        );
                    }

                    $responses[] = [
                        'status' => 'success',
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'type' => $record['type'],
                        'message' => 'Task consideration created or updated successfully.',
                        'data' => $taskConsideration,
                    ];
                } catch (Exception $e) {
                    $responses[] = [
                        'status' => 'error',
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'type' => $record['type'],
                        'message' => 'An unexpected error occurred while processing this record.',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'status' => 'completed',
                'results' => $responses,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed for the entire request.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function AddRequestForTemporaryEnrollment(Request $request)
    {
        try {
            $validated = $request->validate([
                'RegNo' => 'required|string|max:255',
                'teacher_offered_course_id' => 'required|integer',
                'date_time' => 'required|date',
                'venue' => 'required',
                'isLab' => 'required|boolean',
                'status' => 'required|string|max:255',
            ]);
            if (!student::where('RegNo', $validated['RegNo'])->exists()) {
                return response()->json([
                    'error' => 'The provided registration number does not exist.',
                ], 404);
            }
            $student = student::where('RegNo', $validated['RegNo'])->first();
            $currentSession_id = (new session())->getCurrentSessionId();
            $teacher_offered_course = teacher_offered_courses::with(['offeredCourse'])->find($validated['teacher_offered_course_id']);
            $studentEnrollment = student_offered_courses::
                where('offered_course_id', $teacher_offered_course->offeredCourse->id)
                ->where('student_id', $student->id)->first();
            if ($studentEnrollment) {
                return response()->json([
                    'message' => 'The Student is Already Enrolled in Above Subject in Different Section ! . Request Withrawed',
                ], 409);
            }
            $exists = temp_enroll::where([
                'RegNo' => $validated['RegNo'],
                'teacher_offered_course_id' => $validated['teacher_offered_course_id'],
                'date_time' => $validated['date_time'],
                'venue' => $validated['venue'],
                'isLab' => $validated['isLab'],
                'status' => $validated['status'],
            ])->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'A similar enrollment request already exists.',
                ], 409);
            }
            $tempEnroll = temp_enroll::create([
                'RegNo' => $validated['RegNo'],
                'teacher_offered_course_id' => $validated['teacher_offered_course_id'],
                'date_time' => $validated['date_time'],
                'venue' => $validated['venue'],
                'isLab' => $validated['isLab'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'message' => 'Temporary enrollment request added successfully.',
                'data' => $tempEnroll,
            ], 201); // 201 Created
        } catch (Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    public function getTemporaryEnrollmentsRequest()
    {
        try {
            $tempEnrollments = temp_enroll::whereNull('Request_Status')
                ->where('isVerified', 0)
                ->with([
                    'teacherOfferedCourse.teacher',
                    'teacherOfferedCourse.section',
                    'teacherOfferedCourse.offeredCourse.course',
                    'venue',
                ])
                ->get();

            $formattedData = $tempEnrollments->map(function ($enrollment) {
                $student = student::where('RegNo', $enrollment->RegNo)->first();
                $teacherOfferedCourse = $enrollment->teacherOfferedCourse;
                $courseName = optional(optional($teacherOfferedCourse->offeredCourse)->course)->name;
                $sectionName = optional($teacherOfferedCourse->section)->getNameByID($teacherOfferedCourse->section_id);
                $teacherName = optional($teacherOfferedCourse->teacher)->name;
                $session = optional($student->session)->name . '-' . optional($student->session)->year;
                return [
                    'Request id' => $enrollment->id,
                    'RegNo' => $enrollment->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'Student Name' => optional($student)->name,
                    'Teacher Name' => $teacherName,
                    'Course Name' => $courseName,
                    'Section Name' => $sectionName,
                    'Session Name' => $session,
                    'Date Time' => $enrollment->date_time,
                    'Venue' => venue::find($enrollment->venue)->venue ?? null,
                    'Message' => sprintf(
                        'Teacher %s requests to enroll student %s (%s) in section %s for the course %s in the session %s.',
                        $teacherName,
                        optional($student)->name,
                        $enrollment->RegNo,
                        $sectionName,
                        $courseName,
                        $session
                    ),
                ];
            });

            return response()->json([
                'message' => 'Temporary enrollments fetched successfully.',
                'data' => $formattedData,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch temporary enrollments.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function ProcessTemporaryEnrollments(Request $request)
    {
        try {
            $request->validate([
                'verification' => 'required|in:Accepted,Rejected',
                'temp_enroll_id' => 'required',
            ]);
            $verification = $request->verification;
            $tempEnrollId = $request->temp_enroll_id;
            $tempEnrollment = temp_enroll::with([
                'teacherOfferedCourse.offeredCourse.course',
                'teacherOfferedCourse.section',
                'teacherOfferedCourse.teacher',
            ])->find($tempEnrollId);
            if (!$tempEnrollment) {
                throw new Exception('Temporary enrollment record not found.');
            }
            $teacherOfferedCourse = $tempEnrollment->teacherOfferedCourse;
            $student = student::where('RegNo', $tempEnrollment->RegNo)->first();
            if (!$teacherOfferedCourse || !$student) {
                throw new Exception('Related data not found for temporary enrollment.');
            }
            $courseName = optional($teacherOfferedCourse->offeredCourse->course)->name;
            $sectionName = optional($teacherOfferedCourse->section)->getNameByID($teacherOfferedCourse->section_id);
            $teacherName = optional($teacherOfferedCourse->teacher)->name;
            $message = "Student {$student->name} ({$student->RegNo}): Your request for enrollment in course '{$courseName}' ";
            $message .= "for section '{$sectionName}' by teacher '{$teacherName}' ";
            if ($verification === 'Accepted') {
                student_offered_courses::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'offered_course_id' => $teacherOfferedCourse->offered_course_id,
                    ],
                    [
                        'section_id' => $teacherOfferedCourse->section_id,
                        'grade' => null, // Default grade
                        'attempt_no' => 0, // Default attempt number
                    ]
                );
                attendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'teacher_offered_course_id' => $teacherOfferedCourse->id,
                        'date_time' => $tempEnrollment->date_time,
                    ],
                    [
                        'status' => 'p',
                        'isLab' => $tempEnrollment->isLab, // Use the `isLab` from temp_enroll
                        'venue_id' => $tempEnrollment->venue, // Ensure venue ID is set
                    ]
                );
                $message .= 'has been Accepted.';
            } else {
                $message .= 'has been Rejected.';
            }
            if ($tempEnrollment->isLab == 1) {
                $juniorLecturerRecord = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourse->id)->first();

                if ($juniorLecturerRecord) {
                    $juniorLecturer = $juniorLecturerRecord->juniorLecturer; // Fetch juniorLecturer details
                    $juniorLecturerUserId = optional($juniorLecturer)->user_id;
                    notification::create([
                        'title' => 'Enrollment Request Verification',
                        'description' => $message,
                        'url' => null,
                        'notification_date' => now(),
                        'sender' => 'JuniorLecturer',
                        'reciever' => 'Student',
                        'Brodcast' => 0,
                        'TL_sender_id' => $juniorLecturerUserId,
                        'TL_receiver_id' => $student->user_id,
                    ]);
                }
            } else {
                notification::create([
                    'title' => 'Enrollment Request Verification',
                    'description' => $message,
                    'url' => null,
                    'notification_date' => now(),
                    'sender' => 'Teacher',
                    'reciever' => 'Student',
                    'Brodcast' => 0,
                    'TL_sender_id' => $teacherOfferedCourse->teacher->user_id,
                    'TL_receiver_id' => $student->user_id,
                ]);
            }

            // Delete the temporary enrollment record
            $tempEnrollment->delete();

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
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAllOfferedCourses($id)
    {
        $query = "
            SELECT 
                t.name AS teacher_name,
                toc.id AS teacher_offered_course_id,
                toc.section_id,
                toc.offered_course_id,
                c.id AS course_id,
                c.name AS course_name,
                c.code AS course_code,
                c.credit_hours,
                CONCAT(s.name, ' ', s.year) AS Session,
                CONCAT(sec.semester, sec.group) AS Section
            FROM 
                teacher_offered_courses toc
            JOIN 
                teacher t ON toc.teacher_id = t.id
            JOIN 
                offered_courses oc ON toc.offered_course_id = oc.id
            JOIN 
                course c ON oc.course_id = c.id
            JOIN 
                session s ON oc.session_id = s.id
            JOIN 
                section sec ON toc.section_id = sec.id
            WHERE 
                toc.teacher_id = :teacher_id
        ";

        // Execute the query with the teacher ID parameter
        $courses = DB::select($query, ['teacher_id' => $id]);

        // Check if any results were returned
        if (empty($courses)) {
            return response()->json(['error' => 'Teacher not found or no courses available'], 404);
        }

        return response()->json($courses);
    }
    public function YourCourses(Request $request)
    {
        try {
            $jl_id = $request->teacher_id;
            $course = self::getTeacherCourseGroupedByActivePrevious($jl_id);
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
    public static function getTeacherCourseGroupedByActivePrevious($teacherId)
    {
        try {
            $currentSessionId = (new session())->getCurrentSessionId();

            // Fetch data with necessary relationships
            $assignments = teacher_offered_courses::where('teacher_id', $teacherId)
                ->with([
                    'offeredCourse.course',
                    'offeredCourse.session',
                    'section',
                    'teacher',
                    'offeredCourse.course.program'
                ])
                ->get();

            $activeCourses = [];
            $previousCourses = [];
            foreach ($assignments as $assignment) {
                $offeredCourse = $assignment->offeredCourse;
                if (!$offeredCourse || !$offeredCourse->course) {
                    continue;
                }
                $course = $offeredCourse->course;
                $session = $offeredCourse->session;
                $program = $course->program;
                $enrollmentsCount = student_offered_courses::where('offered_course_id', $offeredCourse->id)->where('section_id', $assignment->section->id)->count();
                $courseDetails = [
                    'course_name' => $course->name,
                    'course_code' => $course->code,
                    'credit_hours' => $course->credit_hours,
                    'course_type' => $course->type,
                    'description' => $course->description,
                    'lab' => $course->lab ? 'Yes' : 'No',
                    'prerequisite' => $course->pre_req_main ? $course->pre_req_main : 'Main',
                    'section_name' => $assignment->section ? (new section())->getNameByID($assignment->section->id) : 'Unknown',
                    'session_name' => $session ? $session->name . '-' . $session->year : 'Unknown',
                    'total_enrollments' => $enrollmentsCount,
                    'program_name' => $program ? $program->name : 'N/A',
                    'offered_course_id' => $offeredCourse->id,
                    'teacher_offered_course_id' => $assignment->id,
                ];
                if ($course->lab) {
                    $teacherJuniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $assignment->id)
                        ->with('juniorLecturer')
                        ->first();
                    if ($teacherJuniorLecturer && $teacherJuniorLecturer->juniorLecturer) {
                        $courseDetails['junior_name'] = $teacherJuniorLecturer->juniorLecturer->name ?? 'N/A';
                        $courseDetails['junior_image'] = $teacherJuniorLecturer->juniorLecturer->image ? asset($teacherJuniorLecturer->juniorLecturer->image) : null;
                    } else {
                        $courseDetails['junior_name'] = 'N/A';
                        $courseDetails['junior_image'] = null;
                    }
                }

                if ($offeredCourse->session_id == $currentSessionId) {
                    $activeCourses[] = $courseDetails;
                } else {
                    $previousCourses[] = $courseDetails;
                }
            }

            return [
                'active_courses' => $activeCourses,
                'previous_courses' => collect($previousCourses)->groupBy('session_name'),
            ];
        } catch (Exception $ex) {
            return [
                'active_courses' => [],
                'previous_courses' => [],
            ];
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
    public function getYourActiveJuniorLecturer(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|integer',
        ]);

        $teacher_id = $validated['teacher_id'];

        try {

            $activeCourses = self::getActiveCoursesForTeacher($teacher_id);
            $filteredCourses = [];
            foreach ($activeCourses as $course) {
                $teacherJuniorLecturer = teacher_juniorlecturer::where('teacher_offered_course_id', $course['teacher_offered_course_id'])
                    ->with('juniorLecturer') // Eager load the junior lecturer data
                    ->first();
                if ($teacherJuniorLecturer && $teacherJuniorLecturer->juniorLecturer) {
                    $course['junior_lecturer_name'] = $teacherJuniorLecturer->juniorLecturer->name;
                    $filteredCourses[] = $course; // Add course to the filtered list
                }
            }
            return response()->json($filteredCourses, 200);

        } catch (Exception $ex) {

            return response()->json([
                'error' => 'An error occurred while fetching the active junior lecturer data.',
                'message' => $ex->getMessage(),
            ], 500);
        }
    }
    public function SortedAttendanceList(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'venue_name' => 'nullable',
        ]);
        if ($request->has('venue_name')) {
            $type = str_contains(strtolower($validated['venue_name']), 'lab') ? 'Lab' : 'Class';
        } else {
            $type = 'Class';
        }
      
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])
            ->find($teacherOfferedCourseId);

        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }
       
        $offeredCourseId = $teacherOfferedCourse->offered_course_id;
        $sectionId = $teacherOfferedCourse->section_id;
        $studentCourses = student_offered_courses::where('offered_course_id', $offeredCourseId)
            ->where('section_id', $sectionId)
            ->with('student')
            ->get();
           
        if ($studentCourses->isEmpty()) {
            return response()->json([
                'error' => 'No students found for the given teacher offered course.',
            ], 404);
        }
        $attendanceRecords = Attendance_Sheet_Sequence::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->where('For', $type)
            ->with('student')
            ->orderBy('SeatNumber')
            ->get();
            
        $attendanceMap = $attendanceRecords->keyBy('student_id');
        $sortedStudents = [];
        $unsortedStudents = [];
      
        foreach ($studentCourses as $studentCourse) {
            $student = $studentCourse->student;
            if (!$student) {
                continue;
            }
            $attendanceRecord = $attendanceMap->get($student->id);
            $attendanceData = attendance::getAttendanceBySubject($teacherOfferedCourseId, $student->id);
            if ($attendanceRecord) {
                $sortedStudents[] = [
                    'SeatNumber' => $attendanceRecord->SeatNumber,
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'percentage' => $attendanceData['Total']['percentage'],
                ];
            } else {
                $unsortedStudents[] = [
                    'SeatNumber' => null,
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'percentage' => $attendanceData['Total']['percentage'],
                ];
            }
        }
        usort($sortedStudents, fn($a, $b) => $a['SeatNumber'] <=> $b['SeatNumber']);
        $finalList = array_merge($sortedStudents, $unsortedStudents);

        // Determine the list format
        $listFormat = count($attendanceRecords) > 0 ? 'Sorted' : 'Unsorted';
        return response()->json([
            'success' => true,
            'Course Name' => $teacherOfferedCourse->offeredCourse->course->name,
            'Section Name' => (new section())->getNameByID($teacherOfferedCourse->section->id),
            'List Format' => $listFormat,
            'students' => $finalList,
        ], 200);
    }
    public function ReTakeAttendanceWithStatus(Request $request)
    {
        // Validate request
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'venue_name' => 'nullable',
            'fixed_date' => 'required',
        ]);
        $fixed_date = $validated['fixed_date'];
        if ($request->has('venue_name')) {
            $type = str_contains(strtolower($validated['venue_name']), 'lab') ? 'Lab' : 'Class';
        } else {
            $type = 'Class';
        }
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])
            ->find($teacherOfferedCourseId);

        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }
        $offeredCourseId = $teacherOfferedCourse->offered_course_id;
        $sectionId = $teacherOfferedCourse->section_id;
        $studentCourses = student_offered_courses::where('offered_course_id', $offeredCourseId)
            ->where('section_id', $sectionId)
            ->with('student')
            ->get();
        if ($studentCourses->isEmpty()) {
            return response()->json([
                'error' => 'No students found for the given teacher offered course.',
            ], 404);
        }
        $attendanceRecords = Attendance_Sheet_Sequence::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->where('For', $type)
            ->with('student')
            ->orderBy('SeatNumber')
            ->get();

        $attendanceMap = $attendanceRecords->keyBy('student_id');
        $sortedStudents = [];
        $unsortedStudents = [];
        foreach ($studentCourses as $studentCourse) {
            $student = $studentCourse->student;
            if (!$student) {
                continue;
            }
            $attendanceRecord = $attendanceMap->get($student->id);
            $attendanceData = attendance::getAttendanceBySubject($teacherOfferedCourseId, $student->id);
            if ($attendanceRecord) {
                $sortedStudents[] = [
                    'SeatNumber' => $attendanceRecord->SeatNumber,
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'percentage' => $attendanceData['Total']['percentage'],
                    'attedance_status' => attendance::where('student_id', $student->id)->where('teacher_offered_course_id', $teacherOfferedCourseId)->where('date_time', $fixed_date)->first()->status ?? null,
                ];
            } else {
                $unsortedStudents[] = [
                    'SeatNumber' => null,
                    'student_id' => $student->id,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'percentage' => $attendanceData['Total']['percentage'],
                    'attedance_status' => attendance::where('student_id', $student->id)->where('teacher_offered_course_id', $teacherOfferedCourseId)->where('date_time', $fixed_date)->first()->status ?? null,
                ];
            }
        }
        usort($sortedStudents, fn($a, $b) => $a['SeatNumber'] <=> $b['SeatNumber']);
        $finalList = array_merge($sortedStudents, $unsortedStudents);

        // Determine the list format
        $listFormat = count($attendanceRecords) > 0 ? 'Sorted' : 'Unsorted';
        return response()->json([
            'success' => true,
            'Course Name' => $teacherOfferedCourse->offeredCourse->course->name,
            'Section Name' => (new section())->getNameByID($teacherOfferedCourse->section->id),
            'List Format' => $listFormat,
            'students' => $finalList,
        ], 200);
    }
    public function addAttendanceSeatingPlan(Request $request)
    {
        try {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'students' => 'required|array',
            'students.*.student_id' => 'required|integer', // Each student must have an ID
            'students.*.seatNo' => 'required|integer',
            'attendance_type' => 'nullable|string|in:Class,Lab', // Each student must have a seat number
        ]);
        $attendanceType = $request->attendance_type;
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        Attendance_Sheet_Sequence::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->where('For', $attendanceType)
            ->delete();
        $studentData = $validated['students'];
        foreach ($studentData as $data) {
            Attendance_Sheet_Sequence::create([
                'teacher_offered_course_id' => $teacherOfferedCourseId,
                'student_id' => $data['student_id'],
                'For' => $attendanceType,
                'SeatNumber' => $data['seatNo'],
            ]);
        }
        return response()->json([
            'success' => true,
            'message' => 'Attendance seating plan updated successfully.',
        ], 200);
    } catch (ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);

    } catch (Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating the seating plan.',
            'error' => $e->getMessage(),
        ], 500);
    }
    }
    public function updateTeacherPassword(Request $request)
    {
        try {
            $request->validate([
                'teacher_id' => 'required',
                'password' => 'required',
            ]);
            if (user::where('password', $request->password)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is Already Taken by Another User! Please Try a New One'
                ], 401);
            }
            $responseMessage = $this->updateTeacherPasswordHelper(
                $request->teacher_id,
                $request->password
            );
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => $responseMessage
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 400);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Teacher not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateTeacherPasswordHelper($teacher_id, $newPassword)
    {
        $teacher = teacher::find($teacher_id);
        if (!$teacher) {
            throw new Exception("Teacher not found");
        }

        $user_id = $teacher->user_id;
        if (!$user_id) {
            throw new Exception("User ID not found for the teacher");
        }

        $user = user::where('id', $user_id)->first();
        if (!$user) {
            throw new Exception("User not found for the given user ID");
        }
        $user->update(['password' => $newPassword]);
        return "Password updated successfully for Teacher: $teacher->name";
    }
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
    public function getTaskSubmissionList(Request $request)
    {
        try {
            $task_id = $request->task_id;
            $yask = JuniorLecturerHandling::getStudentListForTaskMarking($task_id);
            return response()->json([
                'status' => 'success',
                'List Of Submission' => $yask,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateTeacherEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required',
            'email' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }
        try {
            if (user::where('email', $request->email)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email is Already Taken by Another User! Please Try a New One'
                ], 401);
            }
            $teacher_id = $request->teacher_id;
            $email = $request->email;

            $teacher = Teacher::find($teacher_id);
            $user = user::find($teacher->user_id);
            if (!$teacher) {
                throw new Exception("Teacher not found");
            }
            $user->update(['email' => $email]);
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => "email updated successfully for Teacher: $user->email"
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function updateTeacherImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required',
            'image' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }
        try {
            $teacher_id = $request->teacher_id;
            $file = $request->file('image');

            $teacher = Teacher::find($teacher_id);
            if (!$teacher) {
               $teacher=juniorLecturer::find($teacher_id);
               if (!$teacher) {
                    throw new Exception("Teacher not found");
                }else{
                    $directory = 'Images/JuniorLecturer';
                }
            }else{
                $directory = 'Images/Teacher';
            }
           
            $storedFilePath = FileHandler::storeFile($teacher->user_id, $directory, $file);
            $teacher->update(['image' => $storedFilePath]);
            return response()->json([
                'status' => 'success',
                'success' => true,
                'message' => "Image updated successfully for Teacher: $teacher->name",
                'data' => [
                    'image_url' => asset($storedFilePath) // Full URL for frontend
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public static function getStudentExamResult(int $teacher_offered_course_id, int $student_id)
    {
        $student = student::find($student_id);
        if (!$student) {
            throw new Exception('No Student Found!');
        }
        $teacherOfferedCourse = teacher_offered_courses::with(['offeredCourse'])->find($teacher_offered_course_id);
        if (!$teacherOfferedCourse) {
            throw new Exception('The Teacher Allocation Does Not Exist!');
        }

        $offeredCourseId = $teacherOfferedCourse->offeredCourse->id;
        $exams = exam::where('offered_course_id', $offeredCourseId)->with(['questions'])->get();

        if ($exams->isEmpty()) {

            throw new Exception('No exams found for the given offered course.');
        }

        $results = [];

        foreach ($exams as $exam) {
            $totalObtainedMarks = 0;
            $totalMarks = $exam->total_marks;
            $solidMarks = $exam->Solid_marks;

            $questionData = [];
            foreach ($exam->questions as $question) {
                $result = student_exam_result::where('question_id', $question->id)
                    ->where('student_id', $student_id)
                    ->where('exam_id', $exam->id)
                    ->first();

                $obtainedMarks = $result ? $result->obtained_marks : 0;
                $totalObtainedMarks += $obtainedMarks;

                $questionData[] = [
                    'Question No' => $question->q_no,
                    'Total Marks' => $question->marks,
                    'Obtained Marks' => $obtainedMarks,
                ];
            }

            $solidObtainedMarks = $solidMarks > 0
                ? ($totalObtainedMarks / $totalMarks) * $solidMarks
                : 0;

            $results[$exam->type] = [
                'Total Marks' => $totalMarks,
                'Solid Marks' => $solidMarks,
                'Total Obtained Marks' => $totalObtainedMarks,
                'Total Solid Obtained Marks' => $solidObtainedMarks,
                'Percentage' => $totalMarks > 0 ? ($totalObtainedMarks / $totalMarks) * 100 : 0,
                'Questions' => $questionData,
            ];
        }

        return $results;
    }
    public function getSectionExamList(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
        ]);
        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])->find($teacherOfferedCourseId);

        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }
        $offeredCourseId = $teacherOfferedCourse->offered_course_id;
        $sectionId = $teacherOfferedCourse->section_id;
        $studentCourses = student_offered_courses::where('offered_course_id', $offeredCourseId)
            ->where('section_id', $sectionId)
            ->with(['student'])
            ->get();

        if ($studentCourses->isEmpty()) {
            return response()->json([
                'error' => 'No students found for the given teacher offered course.',
            ], 404);
        }

        $studentsData = $studentCourses->map(function ($studentCourse) use ($teacherOfferedCourseId) {
            $student = $studentCourse->student;
            if (!$student) {
                return null;
            }
            $examResults = self::getStudentExamResult($teacherOfferedCourseId, $student->id);

            return [
                "student_id" => $student->id,
                "name" => $student->name,
                "RegNo" => $student->RegNo,
                "Exam Results" => $examResults ?: null,
            ];
        })->filter();
        return response()->json([
            'success' => true,
            'Session Name' => ($teacherOfferedCourse->offeredCourse->session->name . '-' . $teacherOfferedCourse->offeredCourse->session->year) ?? null,
            'Course Name' => $teacherOfferedCourse->offeredCourse->course->name,
            'Section Name' => (new section())->getNameByID($teacherOfferedCourse->section->id),
            'students_count' => $studentCourses->count(),
            'students' => $studentsData,
        ], 200);
    }

}
