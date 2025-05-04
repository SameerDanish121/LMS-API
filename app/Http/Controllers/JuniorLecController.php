<?php

namespace App\Http\Controllers;
use App\Models\datacell;
use App\Models\grader_task;
use DateTime;
use Exception;
use App\Models;
use Carbon\Carbon;
use App\Models\exam;
use App\Models\role;
use App\Models\task;
use App\Models\User;
use App\Models\admin;
use App\Models\topic;
use App\Models\venue;
use App\Models\Action;
use App\Models\Course;
use App\Models\grader;
use App\Models\dayslot;
use App\Models\program;
use App\Models\section;
use App\Models\session;

use App\Models\student;
use App\Models\teacher;

use App\Models\question;
use App\Models\timetable;
use Laravel\Pail\Options;
use App\Models\attendance;
use App\Models\FileHandler;
use App\Models\temp_enroll;
use App\Models\notification;
use Illuminate\Http\Request;
use App\Models\coursecontent;
use App\Models\excluded_days;
use App\Models\sessionresult;
use App\Models\juniorlecturer;
use App\Models\quiz_questions;
use App\Models\teacher_grader;
use App\Models\offered_courses;
use App\Models\StudentManagement;
use Illuminate\Support\Facades\DB;
use App\Models\coursecontent_topic;
use App\Models\student_exam_result;
use App\Models\student_task_result;

use App\Models\contested_attendance;
use App\Models\JuniorLecturerHandling;
use App\Models\teacher_juniorlecturer;

use GrahamCampbell\ResultType\Success;

use App\Models\student_offered_courses;

use App\Models\student_task_submission;

use App\Models\teacher_offered_courses;
use function PHPUnit\Framework\isEmpty;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Attendance_Sheet_Sequence;
use Illuminate\Support\Facades\Validator;

use App\Models\t_coursecontent_topic_status;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class JuniorLecController extends Controller
{
    public function YourCurrentSessionCourses(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForJunior($teacher_id);
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
                'CreatedBy' => 'Junior Lecturer',
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
    public static function getActiveCoursesForJunior($teacher_id)
    {
        try {

            $currentSessionId = (new session())->getCurrentSessionId();
            $assignments = teacher_offered_courses::where('teacher_id', $teacher_id)
                ->with(['offeredCourse.course', 'section'])
                ->get();
                $assignments = teacher_offered_courses::whereHas('teacherJuniorLecturer', function ($query) use ($teacher_id) {
                    $query->where('juniorlecturer_id', $teacher_id);
                })
                ->with([
                    'offeredCourse.course',
                    'offeredCourse.session',
                    'section',
                    'teacher',
                    'offeredCourse.course.program'
                ])
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
                        'offered_course_id' => $offeredCourse->id,
                        'teacher_offered_course_id' => $assignment->id,
                        'course_lab' => $offeredCourse->course->lab == 1 ? 'Yes' : 'No',
                        'section_name' => $assignment->section->getNameByID($assignment->section_id),
                        'course_id' => $offeredCourse->course->id,
                    ];
                }
            }
            return $activeCourses;
        } catch (Exception $ex) {
            return [
            ];
        }
    }
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
    public function getTeacherCourseContent(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForJunior($teacher_id);
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
    public function getTaskDetails(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $ActiveCourses = self::getActiveCoursesForJunior($teacher_id);
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
    public static function categorizeTasksForJunior(int $teacher_id)
    {
        try {
            $activeCourses = self::getActiveCoursesForJunior($teacher_id);
            $teacherOfferedCourseIds = collect($activeCourses)->pluck('teacher_offered_course_id');
            $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->whereIn('teacher_offered_course_id', $teacherOfferedCourseIds)
                ->where('CreatedBy', 'Junior Lecturer')
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
    public function YourTaskInfo(Request $request)
    {
        try {
            $teacher_id = $request->teacher_id;
            $task = self::categorizeTasksForJunior($teacher_id);
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
                ->where('junior_lecturer_id', $teacherId)
                ->whereNull('teacher_id')
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

            $teacher = juniorlecturer::find($teacher_id);
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
        $teacher = juniorlecturer::find($teacher_id);
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
    public function getTeacherNotifications(Request $request)
    {
        try {
            $teacherId = $request->teacher_id;
            if (!$teacherId) {
                return response()->json(['status' => false, 'message' => 'teacher_id is required'], 400);
            }
            $teacher = juniorlecturer::find($teacherId);
            if (!$teacher) {
                return response()->json(['status' => false, 'message' => 'Teacher not found'], 404);
            }
            $userId = $teacher->user_id;
            $broadcastNotifications = notification::where('Brodcast', true)
                ->where(function ($query) {
                    $query->where('reciever', 'JuniorLecturer')
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
                    $Datacell =datacell::where('user_id', $note->TL_sender_id)->first();
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
    public function juniorTodayClassesWithStatus($juniorLecturerId)
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
            ->where('timetable.junior_lecturer_id', $juniorLecturerId)
            ->where('timetable.type', 'Lab')
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

        $formattedClasses = $classes->map(function ($class) use ($juniorLecturerId, $currentDate) {
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
            $teacherOfferedCourse = teacher_offered_courses::
                where('section_id', $class->section_id)
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
    public function FullTimetable(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $timetable = JuniorLecturerHandling::getJuniorLecturerFullTimetable($jl_id);
            return response()->json([
                'status' => 'success',
                'data' => $timetable
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
            $assignments = teacher_offered_courses::whereHas('teacherJuniorLecturer', function ($query) use ($teacherId) {
                $query->where('juniorlecturer_id', $teacherId);
            })
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

    public function YourNotification(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $message = JuniorLecturerHandling::getNotificationsForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'data' => $message
            ], 200);
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
                'title' => 'required',
                'user_id' => 'required|integer',
                'message' => 'required|string',
                'is_broadcast' => 'required|boolean',
                'section_name' => 'nullable|string',
                'url' => 'nullable'
            ]);
            $userId = $request->input('user_id');
            $message = $request->input('message');
            $isBroadcast = $request->input('is_broadcast');
            $sectionName = $request->input('section_name', null);
            $title = $request->input('title');
            $url = $request->input('url', null);
            $notification = JuniorLecturerHandling::sendNotification($title, $userId, $message, $isBroadcast, $sectionName, $url);
            return response()->json([
                'status' => 'success',
                'notification' => $notification ? ' Sended Sucessfully' : 'Not Sended !',
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function ActiveCourseInfo(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $notification = JuniorLecturerHandling::getActiveCoursesForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'course' => $notification,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTaskInfo(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $yask = JuniorLecturerHandling::categorizeTasksForJuniorLecturer($jl_id);
            return response()->json([
                'status' => 'success',
                'tasks' => $yask,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
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
    public function attendanceListofLab(Request $request)
    {
        try {
            $toc_id = $request->teacher_offered_course_id;
            $attendance = JuniorLecturerHandling::attendanceListofLab($toc_id);
            return response()->json([
                'status' => 'success',
                'attendance' => $attendance,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function attendanceListofSingleStudent(Request $request)
    {
        try {
            $toc_id = $request->toc_id;
            $student_id = $request->student_id;

            $attendance = JuniorLecturerHandling::FullattendanceListForSingleStudent($toc_id, $student_id);
            return response()->json([
                'status' => 'success',
                'attendance' => $attendance,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // public function storeTask(Request $request)
    // {
    //     try {
    //         $validatedData = $request->validate([
    //             'title' => 'required',
    //             'type' => 'required|in:Quiz,Assignment,LabTask',
    //             'coursecontent_id' => 'required',
    //             'points' => 'required|numeric|min:0',
    //             'start_date' => 'required|date',
    //             'due_date' => 'required|date|after:start_date',
    //             'course_name' => 'required|string|max:255',
    //             'sectioninfo' => 'required|string'
    //         ]);
    //         $courseName = $validatedData['course_name'];
    //         $sectionInfo = $validatedData['sectioninfo'];
    //         $points = $validatedData['points'];
    //         $startDate = $validatedData['start_date'];
    //         $dueDate = $validatedData['due_date'];
    //         $coursecontent_id = $validatedData['coursecontent_id'];
    //         $sections = explode(',', $sectionInfo);
    //         $course = Course::where('name', $courseName)->first();
    //         if (!$course) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => "Course '{$courseName}' not found.",
    //             ], 404);
    //         }
    //         $course_content = coursecontent::find($coursecontent_id);
    //         if (!$course_content) {
    //             return response()->json([
    //                 'status' => 'error',
    //                 'message' => "No Task is Found with given Cradentails ",
    //             ], 404);
    //         }
    //         $type = $course_content->type;
    //         $insertedTasks = [];
    //         foreach ($sections as $sectionName) {
    //             $sectionName = trim($sectionName);
    //             $section = section::addNewSection($sectionName);
    //             if (!$section) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => "Section '{$sectionName}' not found.",
    //                 ], 404);
    //             }

    //             $sectionId = $section;

    //             $courseId = $course->id;

    //             $currrentSession = (new session())->getCurrentSessionId();

    //             $offered_course_id = offered_courses::where('session_id', $currrentSession)
    //                 ->where('course_id', $course->id)->value('id');

    //             $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionId)
    //                 ->where('offered_course_id', $offered_course_id)
    //                 ->first();

    //             if (!$teacherOfferedCourse) {
    //                 return response()->json([
    //                     'status' => 'error',
    //                     'message' => "Teacher-offered course not found for section '{$sectionName}' and course '{$courseName}'.",
    //                 ], 404);
    //             }
    //             $taskNo = Action::getTaskCount($teacherOfferedCourse->id, $type);
    //             if ($taskNo > 0 && $taskNo < 10) {
    //                 $taskNo = "0" . $taskNo;
    //             }

    //             $filename = $course->description . $course_content->title . '-' . $type . $taskNo . '-' . $sectionName;
    //             $title = $filename;
    //             $teacherOfferedCourseId = $teacherOfferedCourse->id;
    //             $taskData = [
    //                 'title' => $title,
    //                 'type' => $type,
    //                 'CreatedBy' => 'Junior Lecturer',
    //                 'points' => $points,
    //                 'start_date' => $startDate,
    //                 'due_date' => $dueDate,
    //                 'coursecontent_id' => $coursecontent_id,
    //                 'teacher_offered_course_id' => $teacherOfferedCourseId,
    //                 'isMarked' => false,
    //             ];
    //             $task = task::where('teacher_offered_course_id', $teacherOfferedCourse)
    //                 ->where('coursecontent_id', $coursecontent_id)->first();
    //             if ($task) {
    //                 $task->update($taskData);
    //                 $insertedTasks[] = ['status' => 'Task is Already Allocated ! Just Updated the informations', 'task' => $task];
    //             } else {
    //                 $task = Task::create($taskData);
    //                 $insertedTasks[] = ['status' => 'Task Allocated Successfully', 'task' => $task];
    //             }

    //         }
    //         return response()->json([
    //             'status' => 'success',
    //             'message' => 'Tasks inserted successfully.',
    //             'tasks' => $insertedTasks,
    //         ], 200);
    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed.',
    //             'errors' => $e->errors(),
    //         ], 422);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'An unexpected error occurred.',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
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
    // {
    //     "attendance_records": [
    //   {
    //     "student_id": 1,
    //     "teacher_offered_course_id": 1,
    //     "status": "p",
    //     "date_time": "2025-01-12 09:00:00",
    //     "isLab": true,
    //     "venue_id": 2
    //   },
    //       {
    //         "student_id": 2,
    //         "teacher_offered_course_id": 1,
    //         "status": "a",
    //         "date_time": "2025-01-12 09:00:00",
    //         "isLab": false,
    //         "venue_id": 3
    //       }
    //     ]
    //   }

    public function markBulkAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'attendance_records' => 'required|array',
            'attendance_records.*.student_id' => 'required',
            'attendance_records.*.teacher_offered_course_id' => 'required',
            'attendance_records.*.status' => 'required|in:p,a',
            'attendance_records.*.date_time' => 'required|date_format:Y-m-d H:i:s',
            'attendance_records.*.isLab' => 'required|boolean',
            'attendance_records.*.venue_id' => 'required',
        ]);

        try {
            $attendanceRecords = $validatedData['attendance_records'];

            foreach ($attendanceRecords as $attendanceData) {
                $teacherCourse = teacher_offered_courses::find($attendanceData['teacher_offered_course_id']);
                $student = student::find($attendanceData['student_id']);

                if (!$teacherCourse || !$student) {
                    continue;
                }
                // attendance::updateOrCreate([
                //     'status' => $attendanceData['status'],
                //     'date_time' => $attendanceData['date_time'],
                //     'isLab' => $attendanceData['isLab'],
                //     'student_id' => $attendanceData['student_id'],
                //     'teacher_offered_course_id' => $attendanceData['teacher_offered_course_id'],
                //     'venue_id' => $attendanceData['venue_id'],
                // ]);
                Attendance::updateOrCreate(
                    [
                        'date_time' => $attendanceData['date_time'],
                        'isLab' => $attendanceData['isLab'],
                        'student_id' => $attendanceData['student_id'],
                        'teacher_offered_course_id' => $attendanceData['teacher_offered_course_id'],
                        'venue_id' => $attendanceData['venue_id'],
                    ],
                    [
                        'status' => $attendanceData['status']
                    ]
                );

            }
            return response()->json(['message' => 'Attendance records marked successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['message' => 'Error marking attendance', 'error' => $e->getMessage()], 500);
        }
    }
    public function getTodayLabClassesWithTeacherCourseAndVenue(Request $request)
    {
        try {
            $juniorLecturerId = $request->juniorLecturerId;
            if (!$juniorLecturerId) {
                return response()->json([
                    'message' => 'Junior Lecturer ID is required'
                ], 400);
            }
            $timetable = JuniorLecturerHandling::getTodayLabClassesWithTeacherCourseAndVenue($juniorLecturerId);
            return response()->json([
                'message' => 'Successfully fetched today\'s lab classes',
                'data' => $timetable
            ], 200);
        } catch (Exception $e) {
            // Handle any potential errors
            return response()->json([
                'message' => 'Error fetching today\'s lab classes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function ContestList(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $jl = juniorlecturer::find($jl_id);
            if (!$jl) {
                throw new Exception('No Record FOR Given id of Teacher found !');
            }
            $contents = contested_attendance::with(['attendance.teacherOfferedCourse.offeredCourse.course'])
                ->whereHas('attendance.teacherOfferedCourse.teacherJuniorLecturer', function ($query) use ($jl_id) {
                    $query->where('juniorlecturer_id', $jl_id);
                })->whereHas('attendance', function ($query) use ($jl_id) {
                    $query->where('isLab', 1);
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
            $message .= "by Junior Lecturer '{$attendance->teacherOfferedCourse->teacher->name}' has been ";
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
    public static function getActiveCoursesForJuniorLecturer($juniorLecturerId)
    {
        try {
            // Get the current session ID
            $currentSessionId = (new session())->getCurrentSessionId();

            // Fetch the assignments for the given junior lecturer
            $assignments = teacher_juniorlecturer::where('juniorlecturer_id', $juniorLecturerId)
                ->with(['teacherOfferedCourse.offeredCourse.course', 'teacherOfferedCourse.section'])
                ->get();

            $activeCourses = [];

            // Iterate over each assignment and check if the course is active in the current session
            foreach ($assignments as $assignment) {
                $offeredCourse = $assignment->teacherOfferedCourse->offeredCourse;
                if (!$offeredCourse) {
                    continue;
                }

                // Get the session ID for the offered course
                $sessionId = $offeredCourse->session_id;
                if ($sessionId == $currentSessionId) {
                    // Add the active course to the array
                    $activeCourses[] = [
                        'course_name' => $offeredCourse->course->name,
                        'teacher_offered_course_id' => $assignment->teacherOfferedCourse->id,
                        'section_name' => $assignment->teacherOfferedCourse->section->getNameByID($assignment->teacherOfferedCourse->section_id),
                    ];
                }
            }

            // Return the active courses
            return $activeCourses;

        } catch (Exception $ex) {
            // Return error if an exception occurs
            return [
                'error' => 'An error occurred while fetching the active courses.',
                'message' => $ex->getMessage(),
            ];
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
    public function getListofUnassignedTask(Request $request)
    {
        try {
            $jl_id = $request->jl_id;
            $activeCourses = self::getActiveCoursesForJuniorLecturer($jl_id);
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
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching unassigned tasks.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public static function getYourTeacher(Request $request)
    {
        try {
            $validated = $request->validate([
                'junior_id' => 'required|integer',
            ]);

            $juniorLecturerId = $validated['junior_id'];
            $activeCourses = self::getActiveCoursesForJuniorLecturer($juniorLecturerId);

            $result = [];

            // Process each active course to fetch teacher details
            foreach ($activeCourses as $course) {
                $teacherOfferedCourseId = $course['teacher_offered_course_id'];
                $teacherOfferedCourse = teacher_offered_courses::where('id', $teacherOfferedCourseId)
                    ->with('teacher') // Assuming 'teacher' is related to 'user' for name
                    ->first();

                if ($teacherOfferedCourse && $teacherOfferedCourse->teacher && $teacherOfferedCourse->teacher->user) {
                    $teacherName = $teacherOfferedCourse->teacher->name;

                    // Add the teacher and course information to the result
                    $result[] = [
                        'teacher_name' => $teacherName,
                        'course_name' => $course['course_name'],
                        'section_name' => $course['section_name'],
                        'teacher_offered_course_id' => $course['teacher_offered_course_id'],
                    ];
                }
            }

            // Return the result array
            return $result;

        } catch (Exception $ex) {
            // Return error if an exception occurs
            return [
                'error' => 'An error occurred while fetching the teacher details.',
                'message' => $ex->getMessage(),
            ];
        }
    }
    public function SortedAttendanceList(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer'
        ]);
        $type = 'Lab';
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
            $attendanceData = attendance::getAttendanceBySubject($teacherOfferedCourseId, $student->id);
            if ($attendanceRecord) {
                $sortedStudents[] = [
                    'SeatNumber' => $attendanceRecord->SeatNumber,
                    'name' => $student->name,
                    'RegNo' => $student->RegNo,
                    'image' => $student->image ? asset($student->image) : null,
                    'Percentage' => $attendanceData['Total']['percentage'],
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
        try {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'students' => 'required|array',
            'students.*.student_id' => 'required|integer', // Each student must have an ID
            'students.*.seatNo' => 'required|integer', // Each student must have a seat number
        ]);
        $attendanceType = 'Lab';
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
    } catch (\Illuminate\Validation\ValidationException $e) {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $e->errors()
        ], 422);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'An error occurred while updating the seating plan.',
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    public function updateJuniorLecturerPassword(Request $request)
    {
        try {
            // Validate incoming data
            $request->validate([
                'junior_lecturer_id' => 'required|integer',
                'newPassword' => 'required|string',
            ]);

            // Check if the password already exists for any other user
            if (user::where('password', $request->newPassword)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is Already Taken by Another User! Please Try a New One'
                ], 401);
            }

            // Fetch junior lecturer and update password
            $responseMessage = $this->updateJuniorLecturerPasswordHelper(
                $request->junior_lecturer_id,
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
                'message' => 'Junior Lecturer not found'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function updateJuniorLecturerPasswordHelper($junior_lecturer_id, $newPassword)
    {
        $juniorLecturer = juniorlecturer::find($junior_lecturer_id);
        if (!$juniorLecturer) {
            throw new Exception("Junior Lecturer not found");
        }

        $user_id = $juniorLecturer->user_id;
        if (!$user_id) {
            throw new Exception("User ID not found for the junior lecturer");
        }

        $user = user::where('id', $user_id)->first();
        if (!$user) {
            throw new Exception("User not found for the given user ID");
        }
        $user->update(['password' => $newPassword]);

        return "Password updated successfully for Junior Lecturer: $juniorLecturer->name";
    }
    public function updateJuniorLecturerImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'junior_lecturer_id' => 'required',
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
            $junior_lecturer_id = $request->junior_lecturer_id;
            $file = $request->file('image');
            $juniorLecturer = JuniorLecturer::find($junior_lecturer_id);
            if (!$juniorLecturer) {
                throw new Exception("Junior Lecturer not found");
            }
            $directory = 'Images/JuniorLecturer';
            $storedFilePath = FileHandler::storeFile($juniorLecturer->user_id, $directory, $file);

            // Update the junior lecturer's image path
            $juniorLecturer->update(['image' => $storedFilePath]);

            return response()->json([
                'success' => true,
                'message' => "Image updated successfully for Junior Lecturer: $juniorLecturer->name"
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

}
