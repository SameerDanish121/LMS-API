<?php

namespace App\Models;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
class JuniorLecturerHandling extends Model
{
    public static function getJuniorLecturerFullTimetable($juniorlec_id)
    {
        if (!$juniorlec_id) {
            return [];
        }
        if (!(new session())->getCurrentSessionId()) {
            return [];
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'section:id'
        ])
            ->where('junior_lecturer_id', $juniorlec_id)
            ->where('session_id', (new session())->getCurrentSessionId())
            ->get()
            ->map(function ($item) {
                return [
                    'day' => $item->dayslot->day,
                    'coursename' => $item->course->name,
                    'description' => $item->course->description,
                    'teachername' => $item->teacher->name ?? 'N/A',
                    'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section->id) ?? 'N/A',
                    'venue' => $item->venue->venue,
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                ];
            });
            $groupedByDay = $timetable->groupBy('day')->map(function ($items, $day) {
                return [
                    'day' => $day,
                    'schedule' => $items->toArray()
                ];
            });
            $groupedByDay = $timetable->groupBy('day')->map(function ($items, $day) {
                return [
                    'day' => $day,
                    'schedule' => $items->toArray()
                ];
            });
    
            return $groupedByDay->values()->toArray();
    }
    public static function getJuniorLecturerCourseGroupedByActivePrevious($juniorLecturerId)
    {
        try {
            $currentSessionId = (new session())->getCurrentSessionId();
            
            // Fetch data with necessary relationships
            $assignments = teacher_juniorlecturer::where('juniorlecturer_id', $juniorLecturerId)
                ->with([
                    'teacherOfferedCourse.offeredCourse.course',
                    'teacherOfferedCourse.offeredCourse.session',
                    'teacherOfferedCourse.section',
                    'teacherOfferedCourse.teacher',
                    'teacherOfferedCourse.offeredCourse.course.program',
                ])
                ->get();
            $activeCourses = [];
            $previousCourses = [];
            foreach ($assignments as $assignment) {
                $teacherOfferedCourse = $assignment->teacherOfferedCourse;
                if (!$teacherOfferedCourse || !$teacherOfferedCourse->offeredCourse || !$teacherOfferedCourse->offeredCourse->course) {
                    continue; 
                }
                $offeredCourse = $teacherOfferedCourse->offeredCourse;
                $course = $offeredCourse->course;
                $session = $offeredCourse->session;
                $program= $course->program;
                $courseDetails = [
                    'teacher_offered_course_id' => $teacherOfferedCourse->id,
                    'course_name' => $course->name,
                    'course_code' => $course->code,
                    'credit_hours' => $course->credit_hours,
                    'course_type' => $course->type,
                    'description' => $course->description,
                    'lab' => $course->lab ? 'Yes' : 'No',
                    'prerequisite' => $course->pre_req_main ? $course->pre_req_main : 'Main',
                    'teacher_name' => $teacherOfferedCourse->teacher ? $teacherOfferedCourse->teacher->name : 'Unknown',
                    'teacher_image' => $teacherOfferedCourse->image ? asset($teacherOfferedCourse->teacher->image):null,
                    'section_name' => $teacherOfferedCourse->section ? $teacherOfferedCourse->section->group : 'Unknown',
                    'session_name' => $session ? $session->name . '-' . $session->year : 'Unknown',
                    'program_name' => $program ? $program->name : 'N/A',
                ];
    
                if ($offeredCourse->session_id == $currentSessionId) {
                    $activeCourses[] = $courseDetails;
                } else {
                    $previousCourses[] = $courseDetails;
                }
            }
    
            return [
                'active_courses' => $activeCourses,
                'previous_courses' => $previousCourses,
            ];
        } catch (Exception $ex) {
            return [
                'active_courses' => [],
                'previous_courses' => [],
            ];
        }
    }
    
    public static function getActiveCoursesForJuniorLecturer($juniorLecturerId)
    {
        try {
            $currentSessionId = (new session())->getCurrentSessionId();
            $assignments = teacher_juniorlecturer::where('juniorlecturer_id', $juniorLecturerId)
                ->with(['teacherOfferedCourse.offeredCourse.course', 'teacherOfferedCourse.section'])
                ->get();
            $activeCourses = [];
            foreach ($assignments as $assignment) {
                $offeredCourse = $assignment->teacherOfferedCourse->offeredCourse;
                if (!$offeredCourse) {
                    continue;
                }
                $sessionId = $offeredCourse->session_id;
                if ($sessionId == $currentSessionId) {
                    $activeCourses[] = [
                        'course_name' => $offeredCourse->course->name,
                        'teacher_offered_course_id' => $assignment->teacherOfferedCourse->id,
                        'section_name' => $assignment->teacherOfferedCourse->section->getNameByID($assignment->teacherOfferedCourse->section_id),
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
    public static function getNotificationsForJuniorLecturer($juniorlec_id)
    {
        if (!$juniorlec_id) {
            return [];
        }
        $user_id = juniorlecturer::find($juniorlec_id)->first()->user_id;
        $notifications = notification::query()
            ->where(function ($query) use ($user_id) {
                $query->where('TL_receiver_id', $user_id)
                    ->where('reciever', 'JuniorLecturer')
                    ->orWhere(function ($q) {
                        $q->where('Brodcast', true)
                            ->where('reciever', 'JuniorLecturer');
                    });
            })
            ->orderBy('notification_date', 'desc')
            ->get();
        $formattedNotifications = $notifications->map(function ($notification) {
            return [
                'time' => Carbon::parse($notification->notification_date)->format('H:i:s'),
                'date' => Carbon::parse($notification->notification_date)->format('Y-m-d'),
                'sender' => $notification->sender,
                'message' => $notification->description,
                'url' => $notification->url,
                'title' => $notification->title,
            ];
        });
        return $formattedNotifications->toArray();
    }
    public static function sendNotification($tile, $userId, $message, $isBroadcast, $sectionName, $url = null)
    {
        try {
            $sectionId = $sectionName ? (new section())->getIDByName($sectionName) : null;
            $currentDateTime = Carbon::now();
            $notificationData = [
                'title' => $tile,
                'description' => $message,
                'url' => $url ?? null,
                'notification_date' => $currentDateTime,
                'sender' => 'JuniorLecturer',
                'reciever' => 'Student',
                'Brodcast' => $isBroadcast ? 1 : 0,
                'TL_sender_id' => $userId,
                'Student_Section' => $isBroadcast ? null : $sectionId,
                'TL_receiver_id' => null,
            ];
            return notification::create($notificationData);
        } catch (Exception $exception) {
            return $exception->getMessage();
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
    public static function categorizeTasksForJuniorLecturer(int $juniorLecturerId)
    {
        try {
            $activeCourses = self::getActiveCoursesForJuniorLecturer($juniorLecturerId);
            $teacherOfferedCourseIds = collect($activeCourses)->pluck('teacher_offered_course_id');
            $tasks = task::with('courseContent')->whereIn('teacher_offered_course_id', $teacherOfferedCourseIds)
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
                $taskInfo = [
                    'task_id' => $task->id,
                    'title' => $task->title,
                    'type' => $task->type,
                    ($task->courseContent->content == 'MCQS') ? 'MCQS' : 'File' => ($task->courseContent->content == 'MCQS')
                        ? Action::getMCQS($task->courseContent->id)
                        : FileHandler::getFileByPath($task->courseContent->content),
                    'created_by' => $task->CreatedBy,
                    'points' => $task->points,
                    'start_date' => $task->start_date,
                    'due_date' => $task->due_date,
                    'is_evaluated' => $task->IsEvaluated,
                    'marking_status' => $task->isMarked ? 'Marked' : 'Un-Marked',
                    'marking_info' => $markingInfo,
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
    public static function getStudentListForTaskMarking($task_id)
    {
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
            $result = $students->map(function ($student) use ($submissions) {
                $submission = $submissions->firstWhere('Student_id', $student->id);
                if ($submission) {
                    $relativePath = str_replace('public/', '', $submission->Answer);
                    if (Storage::disk('public')->exists($relativePath)) {
                        $submission->Answer = asset($submission->Answer);
                    } else {
                        $submission->Answer = null;
                    }
                    return $submission;
                } else {
                    return (object) [
                        'Student_id' => $student->id,
                        'name' => $student->name,
                        'RegNo' => $student->RegNo,
                        'Answer' => null,
                    ];
                }


            });
            return $result;
        } else {
            throw new Exception('No Info Exsist ! ');
        }
    }

    public static function attendanceListofLab($teacher_offered_course_id)
    {
        $teacherOfferedCourse = teacher_offered_courses::find($teacher_offered_course_id);
        if (!$teacherOfferedCourse) {
            throw new Exception('Teacher Offered Course not found');
        }
        $section_id = $teacherOfferedCourse->section_id;
        $offered_course_id = $teacherOfferedCourse->offered_course_id;
        $studentRecords = student_offered_courses::where('section_id', $section_id)
            ->where('offered_course_id', $offered_course_id)
            ->get();
        $attendanceList = [];
        foreach ($studentRecords as $studentRecord) {
            $student_id = $studentRecord->student_id;
            $attendanceData = attendance::getAttendanceBySubject($teacher_offered_course_id, $student_id);
            if (isset($attendanceData['Lab'])) {
                $student = student::find($student_id);
                $attendanceList[] = [
                    'student name' => $student->name,
                    'student Reg No' => $student->RegNo,
                    'total_classes' => $attendanceData['Lab']['total_classes'],
                    'total_present' => $attendanceData['Lab']['total_present'],
                    'total_absent' => $attendanceData['Lab']['total_absent'],
                    'percentage' => $attendanceData['Lab']['percentage'],
                    'student id' => $student_id,
                    'teacher offered course id' => $teacher_offered_course_id
                ];
            }
        }
        return $attendanceList;
    }
    public static function FullattendanceListForSingleStudent($teacher_offered_course_id, $student_id)
    {
        $teacherOfferedCourse = teacher_offered_courses::find($teacher_offered_course_id);
        if (!$teacherOfferedCourse) {
            throw new Exception('Teacher Offered Course not found');
        }
        $attendanceData = attendance::getAttendanceBySubject($teacher_offered_course_id, $student_id);
        return $attendanceData;
    }
    public static function getLabAttendanceList($teacher_offered_course_id)
    {
        if (!$teacher_offered_course_id) {
            return [];
        }
        $teacherOfferedCourse = teacher_offered_courses::find($teacher_offered_course_id);

        if (!$teacherOfferedCourse) {
            return [];
        }
        $offered_course_id = $teacherOfferedCourse->offered_course_id;
        $section_id = $teacherOfferedCourse->section_id;

        $students = student_offered_courses::where('offered_course_id', $offered_course_id)
            ->where('section_id', $section_id)
            ->with('student')
            ->get();

        $labAttendanceList = [];

        foreach ($students as $student) {
            $studentId = $student->student_id;
            $attendanceData = attendance::getAttendanceBySubject($teacher_offered_course_id, $studentId);
            if (isset($attendanceData['Lab'])) {
                $labAttendance = $attendanceData['Lab'];
                $labAttendanceList[] = [
                    'student_id' => $studentId,
                    'regNo' => $student->student->RegNo,
                    'name' => $student->student->name,
                    'percentage' => $labAttendance['percentage'],
                    'total_absent' => $labAttendance['total_absent'],
                    'total_present' => $labAttendance['total_present'],
                    'total_classes' => $labAttendance['total_classes']
                ];
            }
        }

        return $labAttendanceList;
    }
    public static function getTodayLabClassesWithTeacherCourseAndVenue($juniorLecturer_id = null)
    {
        if (!$juniorLecturer_id) {
            return [];
        }
        $currentSessionId = (new session())->getCurrentSessionId();
        if (!$currentSessionId) {
            return [];
        }
        $timetable = timetable::with([
            'course:name,id,description',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
        ])
            ->where('junior_lecturer_id', $juniorLecturer_id)
            ->where('type', 'Lab')
            ->whereHas('dayslot', function ($query) {
                $query->where('day', Carbon::now()->format('l'));
            })
            ->where('session_id', $currentSessionId)
            ->get()
            ->map(function ($item) use ($currentSessionId) {
                $offeredCourse = offered_courses::where('course_id', $item->course->id)
                    ->where('session_id', $currentSessionId)
                    ->first();
                $teacherOfferedCourse = teacher_offered_courses::where('offered_course_id', $offeredCourse->id)
                    ->where('section_id', $item->section_id)
                    ->first();

                return [
                    'coursename' => $item->course->name ?? 'N/A',
                    'description' => $item->course->description ?? 'N/A',
                    'section' => (new section())->getNameByID($item->section_id) ?? 'N/A',
                    'venue' => $item->venue->venue ?? 'N/A',
                    'venue_id' => $item->venue->id ?? null,
                    'day' => $item->dayslot->day ?? 'N/A',
                    'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                    'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                    'teacher_offered_course_id' => $teacherOfferedCourse ? $teacherOfferedCourse->id : 'N/A',
                ];
            });

        return $timetable;
    }
}
