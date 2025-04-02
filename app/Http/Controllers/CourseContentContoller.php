<?php

namespace App\Http\Controllers;

use App\Models\t_coursecontent_topic_status;
use Exception;
use App\Models\topic;
use App\Models\Action;
use App\Models\session;
use Illuminate\Http\Request;
use App\Models\coursecontent;
use App\Models\StudentManagement;
use App\Models\coursecontent_topic;
use App\Models\teacher_offered_courses;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
class CourseContentContoller extends Controller
{
    public static function getCourseContentWithTopics($offered_course_id)
    {
        $courseContents = coursecontent::where('offered_course_id', $offered_course_id)->get();
        $result = [];
        foreach ($courseContents as $courseContent) {
            $week = (int) $courseContent->week;
            if (!isset($result[$week])) {
                $result[$week] = [];
            }
            if ($courseContent->type === 'Notes') {
                $courseContentTopics = coursecontent_topic::where('coursecontent_id', $courseContent->id)->get();
                $topics = [];

                foreach ($courseContentTopics as $courseContentTopic) {
                    $topic = topic::find($courseContentTopic->topic_id);
                    if ($topic) {
                        $topics[] = [
                            'topic_id' => $topic->id,
                            'topic_name' => $topic->title,
                        ];
                    }
                }

                $result[$week][] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    'File' => $courseContent->content ? asset($courseContent->content) : null,
                    'topics' => $topics,
                ];
            } else {
                $result[$week][] = [
                    'course_content_id' => $courseContent->id,
                    'title' => $courseContent->title,
                    'type' => $courseContent->type,
                    'week' => $courseContent->week,
                    $courseContent->type == 'MCQS' ? 'MCQS' : 'File' => $courseContent->content == 'MCQS'
                        ? Action::getMCQS($courseContent->id)
                        : ($courseContent->content ? asset($courseContent->content) : null)
                ];
            }
        }
        ksort($result);
        return $result;
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
                        'teacher_offered_course_id' => $assignment->id,
                        'offered_course_id' => $offeredCourse->id,
                        'course_name' => $offeredCourse->course->name,
                        'section_name' => $assignment->section->getNameByID($assignment->section_id),
                    ];
                }
            }
            return $activeCourses;
        } catch (Exception $ex) {
            return [];
        }
    }
    public function getTeacherCourseContent(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForTeacher($teacher_id);
            $uniqueCourses = collect($ActiveCourses)->unique('offered_course_id')->values()->all();
            $courseContents = [];
            foreach ($uniqueCourses as $course) {
                $courseId = $course['offered_course_id'];
                $coursename = $course['course_name'];
                $AllSection = collect($ActiveCourses)->where('offered_course_id', $courseId)->toArray();
                $courseContent = self::getCourseContentWithTopics($courseId);
                $courseContents[] = [
                    'offered_course_id' => $courseId,
                    'sections' => $AllSection,
                    'course_name' => $coursename,
                    'course_content' => $courseContent,
                ];
            }
            $courseContents = collect($courseContents)->groupBy('offered_course_id')->toArray();
            return response()->json([
                'status' => true,
                'course_contents' => $courseContents,
            ], 200);
        } catch (Exception $ex) {
            return response()->json(['error' => 'An error occurred while fetching course content.'], 500);
        }
    }
    public static function getCourseContentWithTopicsAndStatus($teacher_offered_course_id)
    {
        try {
            $offered_course_id = teacher_offered_courses::where('id', $teacher_offered_course_id)->first()->offered_course_id;

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
                            } else {
                                DB::table('t_coursecontent_topic_status')->insert([
                                    'coursecontent_id' => $courseContent->id,
                                    'topic_id' => $topic->id,
                                    'teacher_offered_courses_id' => $teacher_offered_course_id,
                                    'Status' => 0
                                ]);
                                $stat = 'Not-Covered';
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
                        'file' => $courseContent->content ? asset($courseContent->content) : null,
                        'topics' => $topics,
                    ];
                }
            }
            ksort($result);
            return $result;
        } catch (Exception $ex) {
            return [];
        }
    }
    public function UpdateTopicStatus(Request $request)
    {
        try {
            $request->validate([
                'coursecontent_id' => 'required',
                'topic_id' => 'required',
                'teacher_offered_courses_id' => 'required',
                'Status' => 'required|boolean'
            ]);
            $coursecontent_id = $request->coursecontent_id;
            $topic_id = $request->topic_id;
            $teacher_offered_courses_id = $request->teacher_offered_courses_id;
            $Status = $request->Status;
            DB::table('t_coursecontent_topic_status')->updateOrInsert(
                [
                    'coursecontent_id' => $coursecontent_id,
                    'topic_id' => $topic_id,
                    'teacher_offered_courses_id' => $teacher_offered_courses_id
                ],
                ['Status' => $Status]
            );
            return response()->json([
                'status' => 'success',
                'message' => 'Status updated successfully'
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 400);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid course content or topic ID'
            ], 404);
        } catch (Exception $ex) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $ex->getMessage()
            ], 500);
        }

    }
    public function GetTopicsDetails(Request $request)
    {
        try {
            $request->validate([
                'teacher_offered_course_id' => 'required'
            ]);

            $teacher_offered_course_id = $request->teacher_offered_course_id;

            $responseMessage = self::getCourseContentWithTopicsAndStatus($teacher_offered_course_id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course_Content' => $responseMessage
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
}

