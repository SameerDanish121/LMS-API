<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Attendance_Sheet_Sequence;
use App\Models\coursecontent;
use App\Models\coursecontent_topic;
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
    public function AddFeedback(Request $request)
    {
        try {
            $request->validate([
                'teacher_grader_id' => 'required',
                'feedback' => 'required|string|max:255'
            ]);
            $teacherGrader = teacher_grader::find($request->teacher_grader_id);
            if (!$teacherGrader) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher grader record not found.',
                ], 404);
            }
            $teacherGrader->feedback = $request->feedback;
            $teacherGrader->save();
            return response()->json([
                'success' => true,
                'message' => 'Feedback updated successfully.',
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
            'teacher_offered_course_id' => 'required|integer',
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
                    $groupedTasks[$type]['obtained_display'] = $obtainedMarks . '/' . $totalMarks;
                    $groupedTasks[$type]['percentage'] .= '%';
                }
                $studentInfo = [
                    'Student_Id' => $student->id,
                    'Student_name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'Lab_Task_Percentage' => $groupedTasks['LabTask']['percentage'],
                    'Lab_Task_Obtained Marks' => $groupedTasks['LabTask']['obtained_display'],
                    'Assignment_Task_Percentage' => $groupedTasks['Assignment']['percentage'],
                    'Assignment_Task_Obtained Marks' => $groupedTasks['Assignment']['obtained_display'],
                    'Quiz_Task_Percentage' => $groupedTasks['Quiz']['percentage'],
                    'Quiz_Task_Obtained Marks' => $groupedTasks['Quiz']['obtained_display'],
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
            foreach ($studentIds as $studentId) {
                $attendanceData = attendance::getAttendanceBySubject($teacherOfferedCourseId, $studentId);
                $studentInfo = [
                    'Student_Id' => $studentId,
                    'Student_Name' => student::find($studentId)->name ?? 'Unknown',
                    'RegNo' => student::find($studentId)->RegNo ?? 'Unknown',
                    'Total_Attended_Lectures' => $attendanceData['Total']['total_present'],
                    'Class_Percentage' => $attendanceData['Total']['percentage'],
                ];
                $result[] = $studentInfo;
            }

            return response()->json([
                'Total Number Of Conducted Classes' => $totalConductedClasses,
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

    public function getTodayClassesWithAttendanceStatus($teacherId)
    {
        $currentDay = Carbon::now()->format('l');
        if (excluded_days::checkHoliday()) {
            return response()->json(['message' => excluded_days::checkHolidayReason()], 404);
        }
        if (excluded_days::checkReschedule()) {
            $currentDay = excluded_days::checkRescheduleDay();
        }
        $currentDate = Carbon::now()->toDateString();
        $classes = Timetable::join('venue', 'timetable.venue_id', '=', 'venue.id')
            ->join('course', 'timetable.course_id', '=', 'course.id')
            ->join('session', 'timetable.session_id', '=', 'session.id')
            ->join('section', 'timetable.section_id', '=', 'section.id')
            ->join('program', 'section.program', '=', 'program.name') // Join with the program table
            ->join('dayslot', 'timetable.dayslot_id', '=', 'dayslot.id')
            ->join('teacher', 'timetable.teacher_id', '=', 'teacher.id')
            ->where('timetable.teacher_id', $teacherId)
            ->where('dayslot.day', $currentDay)
            ->select(
                'timetable.id AS timetable_id',
                'timetable.section_id',
                'timetable.course_id AS offered_course_id',
                'venue.venue AS venue_name',
                'venue.id AS venue_id',
                'course.name AS course_name',
                DB::raw("CONCAT(program.name, '-', section.semester, section.group) AS section"),
                'dayslot.day AS day_slot',
                'dayslot.start_time',
                'dayslot.end_time',
                'timetable.type AS class_type'
            )
            ->get();

        if ($classes->isEmpty()) {
            return response()->json(['message' => 'No classes found for today'], 404);
        }

        $formattedClasses = $classes->map(function ($class) use ($teacherId, $currentDate) {
            $offered_course_data = offered_courses::where('course_id', $class->offered_course_id)
                ->where('session_id', (new session())->getCurrentSessionId())
                ->first();

            if (!$offered_course_data) {
                $class->attendance_status = 'Unmarked';
                $class->teacher_offered_course_id = null;
                return $class;
            }
            try {
                $startDateTime = Carbon::parse($currentDate . ' ' . $class->start_time)->toDateTimeString();
            } catch (Exception $e) {
                $class->attendance_status = 'Unmarked';
                $class->teacher_offered_course_id = null;
                return $class;
            }
            $teacherOfferedCourse = teacher_offered_courses::where('teacher_id', $teacherId)
                ->where('section_id', $class->section_id)
                ->where('offered_course_id', $offered_course_data->id)
                ->first();

            $class->teacher_offered_course_id = $teacherOfferedCourse->id ?? null;
            $attendanceMarked = false;
            if ($teacherOfferedCourse) {
                $attendanceMarked = Attendance::where('teacher_offered_course_id', $teacherOfferedCourse->id)
                    ->where('date_time', $startDateTime)
                    ->exists();
            }
            try {
                $class->start_time = Carbon::parse($class->start_time)->format('g:i A');
                $class->end_time = Carbon::parse($class->end_time)->format('g:i A');
            } catch (Exception $e) {
                $class->start_time = 'Invalid Time';
                $class->end_time = 'Invalid Time';
            }

            $class->attendance_status = $attendanceMarked ? 'Marked' : 'Unmarked';

            return $class;
        });

        return response()->json($formattedClasses);
    }
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
                $taskInfo = [
                    'task_id' => $task->id,
                    'Section' => $task->teacherOfferedCourse->section->program . '-' . $task->teacherOfferedCourse->section->semester . $task->teacherOfferedCourse->section->group,
                    'Course Name' => $task->teacherOfferedCourse->offeredCourse->course->name,
                    'title' => $task->title,
                    'type' => $task->type,
                    ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                        ? Action::getMCQS($task->courseContent->id)
                        : asset($task->courseContent->content),
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
                        'section' => (new section())->getNameByID($grader->student->section_id),
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
            'attendance_type' => 'nullable|string|in:Class,Lab',
        ]);

        // Handle attendance_type with fallback
        $type = $validated['attendance_type'] ?? 'Lab'; // Use Lab if attendance_type is null

        $teacherOfferedCourseId = $validated['teacher_offered_course_id'];

        // Retrieve the teacher offered course data
        $teacherOfferedCourse = teacher_offered_courses::with(['section', 'offeredCourse.course', 'offeredCourse.session'])
            ->find($teacherOfferedCourseId);

        if (!$teacherOfferedCourse) {
            return response()->json([
                'error' => 'Teacher offered course not found.',
            ], 404);
        }

        $offeredCourseId = $teacherOfferedCourse->offered_course_id;
        $sectionId = $teacherOfferedCourse->section_id;

        // Fetch student courses
        $studentCourses = student_offered_courses::where('offered_course_id', $offeredCourseId)
            ->where('section_id', $sectionId)
            ->with('student')
            ->get();

        if ($studentCourses->isEmpty()) {
            return response()->json([
                'error' => 'No students found for the given teacher offered course.',
            ], 404);
        }

        // Fetch attendance sheet sequence
        $attendanceRecords = Attendance_Sheet_Sequence::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->where('For', $type)
            ->with('student')
            ->orderBy('SeatNumber')
            ->get();

        $attendanceMap = $attendanceRecords->keyBy('student_id');
        $sortedStudents = [];
        $unsortedStudents = [];

        // Loop through each student course and check for matching attendance records
        foreach ($studentCourses as $studentCourse) {
            $student = $studentCourse->student;
            if (!$student) {
                continue;
            }
            $attendanceRecord = $attendanceMap->get($student->id);

            if ($attendanceRecord) {
                $sortedStudents[] = [
                    'SeatNumber' => $attendanceRecord->SeatNumber,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                ];
            } else {
                $unsortedStudents[] = [
                    'SeatNumber' => null,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                ];
            }
        }
        usort($sortedStudents, fn($a, $b) => $a['SeatNumber'] <=> $b['SeatNumber']);
        // Merge sorted and unsorted students
        $finalList = array_merge($sortedStudents, $unsortedStudents);

        // Determine the list format
        $listFormat = count($attendanceRecords) > 0 ? 'Sorted' : 'Unsorted';

        // Return the JSON response
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
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'attendance_type' => 'nullable|string|in:Class,Lab', 
            'students' => 'required|array', // List of students with seat number
            'students.*.student_id' => 'required|integer', // Each student must have an ID
            'students.*.seatNo' => 'required|integer', 
        ]);
        $attendanceType = $validated['attendance_type']??null;
        if(!$attendanceType){
            $attendanceType="Class";
        }
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
    }
    public function updateTeacherPassword(Request $request)
    {
        try {
            $request->validate([
                'teacher_id' => 'required|integer',
                'newPassword' => 'required|string',
            ]);
            if (user::where('password', $request->newPassword)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is Already Taken by Another User! Please Try a New One'
                ], 401);
            }
            $responseMessage = $this->updateTeacherPasswordHelper(
                $request->teacher_id,
                $request->newPassword
            );
            return response()->json([
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

    public function updateTeacherImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'teacher_id' => 'required',
            'image' => 'required|image',
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
                throw new Exception("Teacher not found");
            }
            $directory = 'Images/Teacher';
            $storedFilePath = FileHandler::storeFile($teacher->user_id, $directory, $file);
            $teacher->update(['image' => $storedFilePath]);
            return response()->json([
                'success' => true,
                'message' => "Image updated successfully for Teacher: $teacher->name"
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
            return 'No Student Found!';
        }
        $teacherOfferedCourse = teacher_offered_courses::with(['offeredCourse'])->find($teacher_offered_course_id);
        if (!$teacherOfferedCourse) {
            return 'The Teacher Allocation Does Not Exist!';
        }

        $offeredCourseId = $teacherOfferedCourse->offeredCourse->id;
        $exams = exam::where('offered_course_id', $offeredCourseId)->with(['questions'])->get();

        if ($exams->isEmpty()) {
            return response()->json([
                'message' => 'No exams found for the given offered course.',
            ], 404);
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
                "Exam Results" => $examResults?: null,
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
