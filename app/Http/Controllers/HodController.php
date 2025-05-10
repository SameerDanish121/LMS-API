<?php

namespace App\Http\Controllers;
use App\Http\Controllers\Controller;
use App\Models\Action;
use App\Models\Course;
use App\Models\coursecontent;
use App\Models\coursecontent_topic;
use App\Models\datacell;
use App\Models\Director;
use App\Models\FileHandler;
use App\Models\Hod;
use App\Models\juniorlecturer;
use App\Models\offered_courses;
use App\Models\program;
use App\Models\quiz_questions;
use App\Models\section;
use App\Models\session;
use App\Models\student;
use App\Models\StudentManagement;
use App\Models\teacher;
use App\Models\topic;
use App\Models\user;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class HodController extends Controller
{
    public function getAllCourseContents()
    {
        try {
            $result = [];
            $offeredCourses = offered_courses::with(['session', 'course'])
                ->whereHas('session') // Make sure session exists
                ->get()
                ->sortByDesc(fn($course) => $course->session->start_date) // Sort by session start_date descending
                ->values(); // Reset keys

            foreach ($offeredCourses as $offeredCourse) {
                if (!$offeredCourse->course || !$offeredCourse->session) {
                    continue;
                }

                $sessionName = $offeredCourse->session->name . '-' . $offeredCourse->session->year;
                $courseName = $offeredCourse->course->name;

                if (!isset($result[$sessionName])) {
                    $result[$sessionName] = [];
                }

                if (!isset($result[$sessionName][$courseName])) {
                    $result[$sessionName][$courseName] = [];
                }

                $courseContents = coursecontent::where('offered_course_id', $offeredCourse->id)
                    ->orderBy('week')
                    ->get();

                if ($courseContents->isEmpty()) {
                    // Mark empty to show it's ready for content addition
                    $result[$sessionName][$courseName] = [];
                    continue;
                }

                foreach ($courseContents as $courseContent) {
                    $week = (int) $courseContent->week;

                    if (!isset($result[$sessionName][$courseName][$week])) {
                        $result[$sessionName][$courseName][$week] = [];
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

                        $result[$sessionName][$courseName][$week][] = [
                            'course_content_id' => $courseContent->id,
                            'title' => $courseContent->title,
                            'type' => $courseContent->type,
                            'week' => $courseContent->week,
                            'File' => $courseContent->content ? asset($courseContent->content) : null,
                            'topics' => $topics,
                        ];
                    } else {
                        $result[$sessionName][$courseName][$week][] = [
                            'course_content_id' => $courseContent->id,
                            'title' => $courseContent->title,
                            'type' => $courseContent->content == 'MCQS' ? 'MCQS' : $courseContent->type,
                            'week' => $courseContent->week,
                            $courseContent->content == 'MCQS' ? 'MCQS' : 'File' =>
                                $courseContent->content == 'MCQS'
                                ? Action::getMCQS($courseContent->id)
                                : ($courseContent->content ? asset($courseContent->content) : null),
                        ];
                    }
                }
                ksort($result[$sessionName][$courseName]); // Sort weeks
            }
            return response()->json([
                'status' => true,
                'message' => 'Course content fetched successfully.',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error fetching course content: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while fetching course content.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function addCourse(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'code' => 'required|string|max:20',
                'name' => 'required|string|max:100',
                'credit_hours' => 'required|integer|min:1',
                'program_name' => 'required|string',
                'type' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:255',
                'lab' => 'required|boolean',
                'pre_req_code' => 'nullable|string|max:20',
            ]);

            // Check if course with the exact code + name already exists
            $exists = Course::where('code', $validated['code'])
                ->where('name', $validated['name'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Course already exists with the same code and name.',
                ], 409);
            }

            // Get program ID from name
            $program = program::where('name', $validated['program_name'])->first();
            if (!$program) {
                return response()->json([
                    'status' => false,
                    'message' => 'Program not found.',
                ], 404);
            }

            // Get prerequisite course ID if valid code is provided
            $preReqId = null;
            if (!empty($validated['pre_req_code'])) {
                $preReqId = Course::where('code', $validated['pre_req_code'])->value('id');
            }

            // Create new course
            $course = Course::create([
                'code' => $validated['code'],
                'name' => $validated['name'],
                'credit_hours' => $validated['credit_hours'],
                'pre_req_main' => $preReqId,
                'program_id' => $program->id,
                'type' => $validated['type'] ?? 'Core',
                'description' => $validated['description'] ?? null,
                'lab' => $validated['lab'],
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Course added successfully.',
                'data' => $course,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed.',
                'errors' => $ve->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error adding course: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Internal server error.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function AddSingleTeacher(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|string',
                'cnic' => 'required',
                'email' => 'nullable',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $name = trim($request->input('name'));
            $dateOfBirth = $request->input('date_of_birth');
            $gender = $request->input('gender');
            $cnic = $request->input('cnic');
            $email = $request->input('email') ?? null;
            $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
            if (teacher::where('cnic', $cnic)->exists()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The teacher with cnic: {$cnic} already exists."
                ], 409);
            }

            $existingUser = user::where('username', $username)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The teacher with username: {$username} already exists."
                ], 409);
            }

            $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
            $password = Action::generateUniquePassword($name);
            $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');

            if (!$userId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed to create or update user for {$name}."
                ], 500);
            }

            $teacher = Teacher::create([
                'user_id' => $userId,
                'name' => $name,
                'date_of_birth' => $formattedDOB,
                'gender' => $gender,
                'email' => $email,
                'cnic' => $cnic
            ]);
            // Handle image upload only if it's provided
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $directory = 'Images/Teacher';
                $storedFilePath = FileHandler::storeFile($teacher->user_id, $directory, $image);
                $teacher->update(['image' => $storedFilePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "The teacher with Name: {$name} was added.",
                'username' => $username,
                'password' => $password
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AddSingleJunior(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|string',
                'cnic' => 'required',
                'email' => 'nullable',
                'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
            ]);

            $name = trim($request->input('name'));
            $dateOfBirth = $request->input('date_of_birth');
            $gender = $request->input('gender');
            $cnic = $request->input('cnic');
            $email = $request->input('email') ?? null;
            $username = strtolower(str_replace(' ', '', $name)) . '_jl@biit.edu';
            if (juniorlecturer::where('cnic', $cnic)->exists()) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The J-LECTURER with cnic: {$cnic} already exists."
                ], 409);
            }

            $existingUser = user::where('username', $username)->first();
            if ($existingUser) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "The teacher with username: {$username} already exists."
                ], 409);
            }

            $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
            $password = Action::generateUniquePassword($name);
            $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');

            if (!$userId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Failed to create or update user for {$name}."
                ], 500);
            }

            $teacher = juniorlecturer::create([
                'user_id' => $userId,
                'name' => $name,
                'date_of_birth' => $formattedDOB,
                'gender' => $gender,
                'email' => $email,
                'cnic' => $cnic
            ]);
            // Handle image upload only if it's provided
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $directory = 'Images/JuniorLecturer';
                $storedFilePath = FileHandler::storeFile($teacher->user_id, $directory, $image);
                $teacher->update(['image' => $storedFilePath]);
            }

            return response()->json([
                'status' => 'success',
                'message' => "The teacher with Name: {$name} was added.",
                'username' => $username,
                'password' => $password
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Data not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function linkTopicsToCourseContent(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'coursecontent_id' => 'required',
                'topics' => 'required|array',
                'topics.*' => 'required|string'
            ]);
            $coursecontentId = $request->coursecontent_id;
            $topics = $request->topics;
            $linkedCount = 0;
            $alreadyLinked = [];
            foreach ($topics as $topicTitle) {
                $topicTitle = trim($topicTitle);
                if ($topicTitle == '')
                    continue;
                $topic = topic::firstOrCreate(['title' => $topicTitle]);
                $exists = coursecontent_topic::where('coursecontent_id', $coursecontentId)
                    ->where('topic_id', $topic->id)
                    ->exists();
                if (!$exists) {
                    // Link topic to course content
                    coursecontent_topic::create([
                        'coursecontent_id' => $coursecontentId,
                        'topic_id' => $topic->id
                    ]);
                    $linkedCount++;
                } else {
                    $alreadyLinked[] = $topicTitle;
                }
            }
            $message = "{$linkedCount} Topic" . ($linkedCount !== 1 ? 's' : '') . " linked with Course Content.";
            if (!empty($alreadyLinked)) {
                $message .= " And " . count($alreadyLinked) . " Topic" . (count($alreadyLinked) !== 1 ? 's are' : ' is') . " already linked: " . implode(", ", $alreadyLinked) . ".";
            }
            return response()->json(['message' => $message], 200);
        } catch (Exception $e) {
            return response()->json(['error' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }
    public function getGroupedOfferedCoursesBySession()
    {
        try {
            $groupedCourses = offered_courses::with(['course', 'session'])
                ->whereHas('course') // Ensure course exists
                ->whereHas('session') // Ensure session exists
                ->get()
                ->groupBy(function ($offeredCourse) {
                    $session = $offeredCourse->session;
                    return $session ? $session->name . '-' . $session->year : 'Unknown Session';
                });
            $sortedGroupedCourses = $groupedCourses->sortByDesc(function ($courses, $sessionName) {
                return optional($courses->first()->session)->start_date;
            });

            $result = [];

            foreach ($sortedGroupedCourses as $sessionName => $courses) {
                $courseList = [];
                foreach ($courses as $course) {
                    if ($course->course && $course->session) {
                        $courseList[] = [
                            'course' => $course->course->name . ' (' . $course->course->code . ')',
                            'offered_course_id' => $course->id
                        ];
                    }
                }

                if (!empty($courseList)) {
                    $result[] = [
                        'session' => $sessionName,
                        'courses' => $courseList
                    ];
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Offered courses grouped by session retrieved successfully.',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching grouped offered courses: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch offered courses.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function AddMultipleCourseContents(Request $request)
    {
        $results = [];
        $errors = [];

        foreach ($request->all() as $index => $item) {
            try {
                $validated = Validator::make($item, [
                    'week' => 'required|integer',
                    'offered_course_id' => 'required|exists:offered_courses,id',
                    'type' => 'required|in:Quiz,Assignment,Notes,LabTask,MCQS',
                    'file' => 'nullable|file',
                    'MCQS' => 'nullable|array'
                ])->validate();

                $week = $item['week'];
                $type = $item['type'];
                $offered_course_id = $item['offered_course_id'];
                $offeredCourse = offered_courses::with(['course', 'session'])->find($offered_course_id);

                if (!$offeredCourse) {
                    throw new Exception("Offered course not found.");
                }

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
                            throw new Exception("Week Notes already uploaded for Week $week by another teacher.");
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

                    if (!isset($item['file'])) {
                        throw new Exception("File is required for type $type.");
                    }

                    $file = $item['file'];

                    if (!in_array($file->getClientOriginalExtension(), ['pdf', 'doc', 'docx'])) {
                        throw new Exception("Invalid file format. Only pdf, doc, docx allowed.");
                    }

                    $directory = $offeredCourse->session->name . '-' . $offeredCourse->session->year . '/CourseContent/' . $offeredCourse->course->description;
                    $filePath = FileHandler::storeFile($title, $directory, $file);

                    coursecontent::updateOrCreate(
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

                    $results[] = "[$index] Success: $type uploaded for Week $week with title: $title";
                } else if ($type == 'MCQS') {
                    if (!isset($item['MCQS']) || !is_array($item['MCQS'])) {
                        throw new Exception("MCQS array is missing or invalid.");
                    }

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

                    foreach ($item['MCQS'] as $mcq) {
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
                            throw new Exception("Incomplete MCQ data in record $index.");
                        }

                        $quizQuestion = quiz_questions::create([
                            'question_no' => $questionNo,
                            'question_text' => $questionText,
                            'points' => $points,
                            'coursecontent_id' => $courseContent->id,
                        ]);

                        foreach ($options as $optionText) {
                            if ($optionText) {
                                \App\Models\options::create([
                                    'quiz_question_id' => $quizQuestion->id,
                                    'option_text' => $optionText,
                                    'is_correct' => trim($optionText) === trim($answer),
                                ]);
                            }
                        }
                    }

                    $results[] = "[$index] Success: MCQS Quiz added for Week $week with title: $title";
                }

            } catch (ValidationException $e) {
                $errors[] = "[$index] Validation Error: " . implode(', ', array_map(function ($m) {
                    return implode(', ', $m);
                }, $e->errors()));
            } catch (Exception $e) {
                $errors[] = "[$index] Failed: " . $e->getMessage();
            }
        }

        return response()->json([
            'status' => 'completed',
            'success_messages' => $results,
            'error_messages' => $errors,
        ]);
    }

}

