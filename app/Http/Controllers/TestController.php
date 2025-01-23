<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\course;
use App\Models\coursecontent;
use App\Models\coursecontent_topic;
use App\Models\FileHandler;
use App\Models\offered_courses;
use App\Models\quiz_questions;
use App\Models\section;
use App\Models\student_offered_courses;
use App\Models\t_coursecontent_topic_status;
use App\Models\task;
use App\Models\teacher;
use Illuminate\Http\Request;
use App\Models\session;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
class TestController extends Controller
{
    public function upload(Request $request)
    {
        try {
            $tasks = task::with(['courseContent', 'teacherOfferedCourse.offeredCourse.course'])->get();
            $task_details = $tasks->map(function ($task) {
                return [
                    "id" => $task->id,
                    "type" => $task->type,
                    "start_date" => $task->start_date,
                    "end_date" => $task->due_date,
                    "created By" => $task->CreatedBy,
                    "points" => $task->points,
                    "title" => $task->title,
                    "Course" => $task->teacherOfferedCourse->offeredCourse->course->name,
                    "Section" => (new section())->getNameByID($task->teacherOfferedCourse->section_id),
                    "Marked Status" => $task->isMarked ? 'Marked' : 'Un-Marked',
                    "Pre Title" => $task->courseContent->title,
                    "For Week" => $task->courseContent->week,
                    "Pre Type" => $task->courseContent->type,
                    ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                        ? self::getMCQS($task->courseContent->id)
                        : FileHandler::getFileByPath($task->courseContent->content),
                ];
            });
            return $task_details;
        } catch (Exception $ex) {
            return $ex->getMessage();
        }
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
    public function Empty(Request $request)
    {
        try {
            // $task_id =FileHandler::copyFileToDestination('storage/BIIT/Fall-2024/Course Content/CC/CC-Week1-Lec(1-2).pdf','Shampi','Personal');
            $task_id = session::getCurrentSessionWeek();
            return response()->json(
                [
                    'message' => 'Fetched Successfully',
                    'Current Session' => $task_id
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
    public function CopySemester(Request $request)
    {
        try {
            $validated = $request->validate([
                'course_name' => 'required|string',
                'source_session' => 'required|string',
                'destination_session' => 'required|string',
            ]);

            $courseName = $validated['course_name'];
            $sourceSessionName = $validated['source_session'];
            $destinationSessionName = $validated['destination_session'];
            $course = new Course();
            $courseId = $course->getIDByName($courseName);
            $course = course::find($courseId);
            if (!$courseId) {
                return response()->json(['error' => 'Course not found.'], 404);
            }
            $session = new Session();
            $sourceSessionId = $session->getSessionIdByName($sourceSessionName);
            $destinationSessionId = $session->getSessionIdByName($destinationSessionName);

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
                        $directory = "{$destinationSessionName}/Course Content/{$course->description}";
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
                        if($content->type==='Notes'){
                            $existingTopics = coursecontent_topic::where('coursecontent_id', $content->id)->get();
                            foreach ($existingTopics as $topic) {
                                $data = [
                                    'coursecontent_id' => $newContent->id,
                                    'topic_id' => $topic->topic_id,
                                ];
                                $updatedTopic = coursecontent_topic::updateOrCreate(
                                    [
                                        'coursecontent_id' =>$newContent->id,
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
    public function updateCourseContentTopicStatus(Request $request)
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
            $courseContentTopic =coursecontent_topic::firstOrCreate(
                [
                    'coursecontent_id' => $courseContentId,
                    'topic_id' => $topicId,
                ]
            );
            $courseContentTopicStatus =t_coursecontent_topic_status::updateOrCreate(
                [
                    'teacher_offered_courses_id' => $teacherOfferedCourseId,
                    'coursecontent_id' => $courseContentId,
                    'topic_id' => $topicId,
                ],
                [
                    'Status' => $status,
                ]
            );

            return response()->json([
                'message' => 'Course content topic status updated successfully.',
                'coursecontent_topic' => $courseContentTopic,
                'coursecontent_topic_status' => $courseContentTopicStatus,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'An unexpected error occurred: ' . $e->getMessage(),
            ], 500);
        }
    }
    
}



