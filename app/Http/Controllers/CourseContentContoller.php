<?php

namespace App\Http\Controllers;
use App\Models\FileHandler;
use App\Models\offered_courses;
use App\Models\quiz_questions;
use App\Models\section;
use App\Models\t_coursecontent_topic_status;
use App\Models\task;
use Exception;
use App\Models\topic;
use App\Models\Action;
use App\Models\session;
use Illuminate\Http\Request;
use App\Models\coursecontent;
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
                        'course_id' => $offeredCourse->course->id,
                        'course_lab' => $offeredCourse->course->lab == 1 ? 'Yes' : 'No',
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
  
    public function CreateCourseContent(Request $request)
    {
        try {
            $request->validate([
                'data' => 'required|array',
                'offered_course_id' => 'required|exists:offered_courses,id',
            ]);

            $offeredCourse = offered_courses::where('id', $request->offered_course_id)
                ->with(['course', 'session'])
                ->first();

            if (!$offeredCourse) {
                return response()->json(['message' => 'Offered course not found'], 404);
            }

            $successfull = [];
            $index = 0;
            foreach ($request->data as $row) {
                $type = $row['type'] ?? null;
                $weekNo = $row['WeekNo'] ?? null;
                $file = $row['file'] ?? null;
                if (is_null($type) || is_null($weekNo)) {
                    $successfull[] = [
                        "status" => "error",
                        "logs" => "Row skipped: Missing type or WeekNo.",
                    ];
                    continue;
                }

                $weekNo = (int) $weekNo;
                $title = $offeredCourse->course->description . '-Week' . $weekNo;

                if (in_array($type, ['Quiz', 'Assignment'])) {
                    $existingCount = coursecontent::where('week', $weekNo)
                        ->where('offered_course_id', $offeredCourse->id)
                        ->where('type', $type)
                        ->count();
                    $title .= '-' . $type . '-(' . ($existingCount + 1) . ')'; // Replacing `#` with `-`
                }
                if ($type === 'Notes') {
                    $lecNo = ($weekNo * 2) - 1;
                    $lecNo1 = $weekNo * 2;
                    $title .= '-Lec-' . $lecNo . '-' . $lecNo1; // Replacing `()` with `-`
                }
                // Remove special characters (except -, _ and .)
                $title = preg_replace('/[^A-Za-z0-9._-]/', '_', $title);
                $courseContent = coursecontent::updateOrCreate(
                    [
                        'title' => $title,
                        'week' => $weekNo,
                        'offered_course_id' => $offeredCourse->id,
                        'type' => $type,
                    ]
                );
                if ($file === 'MCQS' && isset($row['MCQS']) && is_array($row['MCQS'])) {
                    $courseContent->type = 'Quiz';
                    $courseContent->content = 'MCQS';
                    $courseContent->save();
                    foreach ($row['MCQS'] as $mcq) {
                        $questionNo = $mcq['qNO'] ?? null;
                        $questionText = $mcq['question_text'] ?? null;
                        $points = $mcq['points'] ?? null;
                        $options = [
                            $mcq['option1'] ?? null,
                            $mcq['option2'] ?? null,
                            $mcq['option3'] ?? null,
                            $mcq['option4'] ?? null,
                        ];
                        $answer = $mcq['Answer'] ?? null;
                        if (!$questionNo || !$questionText || !$points || !$answer) {
                            $successfull[] = [
                                "status" => "error",
                                "logs" => "Row skipped: Missing MCQS question data.",
                            ];
                            continue;
                        }
                        $quizQuestion = quiz_questions::create([
                            'question_no' => $questionNo,
                            'question_text' => $questionText,
                            'points' => $points,
                            'coursecontent_id' => $courseContent->id,
                        ]);

                        foreach ($options as $index => $optionText) {
                            if ($optionText) {
                                \App\Models\options::create([
                                    'quiz_question_id' => $quizQuestion->id,
                                    'option_text' => $optionText,
                                    'is_correct' => trim($optionText) === trim($answer),
                                ]);
                            }
                        }
                    }
                } else {
                    if (in_array($file->getClientOriginalExtension(), ['pdf', 'doc', 'docx', 'xlsx', 'xls'])) {
                        $directory = $offeredCourse->session->name . '-' . $offeredCourse->session->year . '/CourseContent' . '/' . $offeredCourse->course->description;
                        $filePath = FileHandler::storeFile($title, $directory, $file);
                        $courseContent->content = $filePath;
                        $courseContent->save();
                    } else {
                        $successfull[] = [
                            "status" => "error",
                            "logs" => "Row skipped: Invalid file type.",
                        ];
                        continue;
                    }
                }
                $successfull[] = [
                    "status" => "success",
                    "logs" => "The content for Week {$weekNo} ({$type}) has been added successfully."
                ];
            }
            return response()->json([
                'message' => 'Successfully added course content!',
                'Stats' => $successfull,
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
    public function AddSingleCourseContent(Request $request)
    {
        try {
            $request->validate([
                'week' => 'required|integer',
                'offered_course_id' => 'required|exists:offered_courses,id',
                'type' => 'required|in:Quiz,Assignment,Notes,LabTask,MCQS',
                'file' => 'nullable|file',
                'MCQS' => 'nullable|array'
            ]);
            $week = $request->week;
            $type = $request->type;
            $offered_course_id = $request->offered_course_id;
            $offeredCourse = offered_courses::where('id', $offered_course_id)
                ->with(['course', 'session'])
                ->first();
            $weekNo = (int) $week;
            $title = $offeredCourse->course->description . '-Week' . $weekNo;
            if (in_array($type, ['Quiz', 'Assignment', 'Notes', 'LabTask'])) {
                if ($type === 'Notes') {
                    if (
                        coursecontent::where('type', $type)
                            ->where('offered_course_id', $offered_course_id)
                            ->where('week', $week)
                            ->whereNotNull('content')
                            ->exists()
                    ) {
                        throw new Exception('The Week Notes Are Already Uploaded By Different Teacher, Can’t Overwrite the Existing Week Notes');
                    }
                    $lecNo = ($weekNo * 2) - 1;
                    $lecNo1 = $weekNo * 2;
                    $title .= '-Lec-' . $lecNo . '-' . $lecNo1;
                } else {
                    $existingCount = coursecontent::where('week', $weekNo)
                        ->where('offered_course_id', $offeredCourse->id)
                        ->where('type', $type)
                        ->count();
                    $title .= '-' . $type . '-' . ($existingCount + 1) . ')';
                }
                $title = preg_replace('/[^A-Za-z0-9._-]/', '_', $title);
                if ($request->has('file')) {
                    $file = $request->file;
                } else {
                    throw new Exception('File is Required for QUIZ,ASSIGNMENT AND NOTES');
                }

                if (in_array($file->getClientOriginalExtension(), ['pdf', 'doc', 'docx'])) {
                    $directory = $offeredCourse->session->name . '-' . $offeredCourse->session->year . '/CourseContent' . '/' . $offeredCourse->course->description;
                    $filePath = FileHandler::storeFile($title, $directory, $file);
                    $courseContent = coursecontent::updateOrCreate(
                        [
                            'week' => $weekNo,
                            'offered_course_id' => $offeredCourse->id,
                            'type' => $type,
                            'title' => $title,
                        ],
                        [
                            'content' => $filePath,
                        ]
                    );
                } else {
                    throw new Exception('THE FILE FORMAT IS NOT SUPPORTED PLEASE TRY pdf , doc , docx ');
                }
            } else if ($type == 'MCQS') {
                
                if ($request->has('MCQS') && isset($request->MCQS) && is_array($request->MCQS)) {
                    $type = 'Quiz';
                    $content = 'MCQS';
                    $existingCount = coursecontent::where('week', $weekNo)
                        ->where('offered_course_id', $offeredCourse->id)
                        ->where('type', $type)
                        ->count();
                    $title .= '-' . $type . '-(' . ($existingCount + 1) . ')';
                    $courseContent = coursecontent::updateOrCreate(
                        [
                            'week' => $weekNo,
                            'offered_course_id' => $offeredCourse->id,
                            'type' => $type,
                            'title' => $title,
                            'content' => $content,
                        ]
                    );
                    foreach ($request->MCQS as $mcq) {
                        $questionNo = $mcq['qNO'] ?? null;
                        $questionText = $mcq['question_text'] ?? null;
                        $points = $mcq['points'] ?? null;
                        $options = [
                            $mcq['option1'] ?? null,
                            $mcq['option2'] ?? null,
                            $mcq['option3'] ?? null,
                            $mcq['option4'] ?? null,
                        ];
                        $answer = $mcq['Answer'] ?? null;
                        if (!$questionNo || !$questionText || !$points || !$answer) {
                            throw new Exception('Un Complete Data to Create MCQS');
                        }
                        $quizQuestion = quiz_questions::create([
                            'question_no' => $questionNo,
                            'question_text' => $questionText,
                            'points' => $points,
                            'coursecontent_id' => $courseContent->id,
                        ]);

                        foreach ($options as $index => $optionText) {
                            if ($optionText) {
                                \App\Models\options::create([
                                    'quiz_question_id' => $quizQuestion->id,
                                    'option_text' => $optionText,
                                    'is_correct' => trim($optionText) === trim($answer),
                                ]);
                            }
                        }
                    }
                } else {
                    throw new Exception('Invalid Data to create MCQS  ');
                }
            }
            return response()->json([
                'status' => 'success',
                'message' => "Successfully Created New $type for Week $week with title named : $title",
            ], 200);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                foreach ($messages as $message) {
                    $errorMessages[] = "$field: $message";
                }
            }
            $errorText = implode(", ", $errorMessages);
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'error' => $errorText,
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function YourCurrentSessionCourses(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForTeacher($teacher_id);
            $uniqueCourses = collect($ActiveCourses)->unique('offered_course_id')->values()->all();
            $courses = [];
            foreach ($uniqueCourses as $course) {
                $courseId = $course['offered_course_id'];
                $coursename = $course['course_name'];
                $courselab = $course['course_lab'];
                $AllSection = collect($ActiveCourses)->where('offered_course_id', $courseId)->toArray();
                $courses[] = [
                    'offered_course_id' => $courseId,
                    'course_name' => $coursename,
                    'course_of_lab' => $courselab,
                    'sections' => $AllSection

                ];
            }
            return response()->json([
                'status' => 'success',
                'courses' => $courses,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function getTaskDetails(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForTeacher($teacher_id);
            $uniqueCourses = collect($ActiveCourses)->unique('offered_course_id')->values()->all();
            $courseContents = [];
            foreach ($uniqueCourses as $course) {
                $courseId = $course['offered_course_id'];
                $coursename = $course['course_name'];
                $course_lab=$course['course_lab'];
                $AllSection = collect($ActiveCourses)->where('offered_course_id', $courseId)->toArray();
                $AllTeacherOfferedCourseIds = collect($AllSection)->pluck('teacher_offered_course_id')->toArray();
                $courseContents[] = [
                    'offered_course_id' => $courseId,
                    'course_name' => $coursename,
                    'course_lab'=>$course_lab,
                    'sections' => $AllSection,
                    'task_details' => self::TaskDetailsOfASubject($courseId,$AllTeacherOfferedCourseIds)
                ];
            }
            $courseContents = collect($courseContents)->groupBy('offered_course_id')->toArray();
            return response()->json([
                'status' => true,
                'data' => $courseContents,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }

    }
    public static function TaskDetailsOfASubject($offered_course_id, $All_teacher_offered_course_ids)
    {
        $details = [];
        try {
            $task_all = coursecontent::where('offered_course_id', $offered_course_id)
                ->whereIn('type', ['Assignment', 'LabTask', 'Quiz'])
                ->get();
    
            $task_with_data = $task_all->map(function ($task) use ($All_teacher_offered_course_ids) {
                if ($task->content == null) {
                    $type = $task->type;
                    $content = null;
                } elseif ($task->content == 'MCQS') {
                    $type = 'Quiz ( MCQS )';
                    $content = Action::getMCQS($task->id);
                } else {
                    $type = $task->type;
                    $content = asset($task->content);
                }
    
                $unassigned_to = [];
                $assigned_to=[];
                foreach ($All_teacher_offered_course_ids as $teacher_offered_course_id) {
                  
                    $section=(new section())->getNameByID(  teacher_offered_courses::find($teacher_offered_course_id)->section_id);
                    if(!(task::where('coursecontent_id',$task->id)->where('teacher_offered_course_id',$teacher_offered_course_id)->exists() )){
                        $unassigned_to[]=['teacher_offered_course_id'=> $teacher_offered_course_id,
                          'section_name'=>$section];
                    }else{
                    $assigned_to[]=['teacher_offered_course_id'=> $teacher_offered_course_id,
                          'section_name'=>$section];
                    }
                }
                return [
                    'id' => $task->id,
                    'type' => $type,
                    'title' => $task->title,
                    'week'=>$task->week,
                    'content' => $content,
                    'un_assigned_to' => $unassigned_to,
                    'assigned_to'=>$assigned_to
                ];
            });
            $data=collect($task_with_data)->groupBy('week')->toArray();
             ksort($data);
            return $data;
        } catch (ValidationException $e) {
            return [];
        } catch (Exception $e) {
            return [];
        }
    }
    public function storeTask(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'coursecontent_id' => 'required|exists:coursecontent,id',
                'points' => 'required|numeric|min:0',
                'start_date' => 'required|date_format:Y-m-d H:i:s',
                'due_date' => 'required|date_format:Y-m-d H:i:s|after:start_date',
                'teacher_offered_course_id' => 'required|exists:teacher_offered_courses,id'
            ]);
    
            $teacher_offered_course_id = $validatedData['teacher_offered_course_id'];
            $points = $validatedData['points'];
            $startDate = $validatedData['start_date'];
            $dueDate = $validatedData['due_date'];
            $coursecontent_id = $validatedData['coursecontent_id'];
    
            // Fetch course content type and title
            $course_content = CourseContent::findOrFail($coursecontent_id);
            $type = $course_content->type;
            $title = $course_content->title;
    
            // Generate unique task title
            $taskNo = Action::getTaskCount($teacher_offered_course_id, $type);
            $taskNo = ($taskNo > 0 && $taskNo < 10) ? "0" . $taskNo : $taskNo;
            $filename = $title . '-' . $type . $taskNo;
            
            $taskData = [
                'title' => $filename,
                'type' => $type,
                'CreatedBy' => 'Teacher',
                'points' => $points,
                'start_date' => $startDate,
                'due_date' => $dueDate,
                'coursecontent_id' => $coursecontent_id,
                'teacher_offered_course_id' => $teacher_offered_course_id
            ];
    
            // Check if a task with the same teacher_offered_course_id & coursecontent_id exists
            $task = Task::where('teacher_offered_course_id', $teacher_offered_course_id)
                ->where('coursecontent_id', $coursecontent_id)
                ->first();
    
            if ($task) {
                $task->update($taskData);
                $response = ['status' => 'Task Already Allocated! Updated Successfully', 'task' => $task];
            } else {
                $task = Task::create($taskData);
                $response = ['status' => 'Task Allocated Successfully', 'task' => $task];
            }
    
            return response()->json([
                'status' => 'success',
                'message' => 'Task processed successfully.',
                'data' => $response
            ], 200);
    
        } catch (ValidationException $e) {
            // Convert errors array to a single string message
            $errorMessage = implode(' ', array_map(function ($error) {
                return implode(' ', $error);
            }, $e->errors()));
    
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed.',
                'error' => $errorMessage, // Single string error
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getTaskforTeacher(Request $request){
        $teacher_id=$request->teacher_id;
        $courses=self::getActiveCoursesForTeacher($teacher_id);
        return $courses;
    }
    
}

