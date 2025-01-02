<?php

namespace App\Http\Controllers;
use App\Models\Action;
use App\Models\admin;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Mail;
use App\Models\datacell;
use App\Models\teacher;
use Illuminate\Support\Facades\Storage;
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
use Exception;
use Illuminate\Http\Request;
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\select;
class StudentController extends Controller
{

    public function AttendancePerSubject(Request $request)
    {
        try {
            $teacher_offered_course_id = $request->teacher_offered_course_id;
            $student_id = $request->student_id;
            $session_id = (new session())->getCurrentSessionId();
            $attendance = attendance::where('teacher_offered_course_id', $teacher_offered_course_id)
                ->where('student_id', $student_id)
                ->select(['status', 'date_time', 'isLab'])
                ->get();
            $totalPresent = $attendance->where('status', 'p')->count();
            $totalAbsent = $attendance->where('status', 'a')->count();
            $total_Classes = $totalPresent + $totalAbsent;
            $percentage = ($totalPresent / $total_Classes) * 100;
            return response()->json([
                'status' => 'Attendance Fetched Successfully',
                'Percentage' => $percentage,
                'Presents' => $totalPresent,
                'Absents' => $totalAbsent,
                'Attendance' => $attendance
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function FullTimetable(Request $request)
    {
        try {
            $section_id = $request->section_id;
            $timetable = Timetable::with([
                'course:name,id,description',
                'teacher:name,id',
                'venue:venue,id',
                'dayslot:day,start_time,end_time,id',
                'juniorLecturer:id,name'
            ])
                ->where('section_id', $section_id)
                ->where('session_id', (new session())->getCurrentSessionId())
                ->get()
                ->map(function ($item) {
                    return [
                        'coursename' => $item->course->name,
                        'description' => $item->course->description,
                        'teachername' => $item->teacher->name ?? 'N/A',
                        'juniorlecturer' => $item->juniorLecturer ? $item->juniorLecturer->name : 'N/A',
                        'venue' => $item->venue->venue,
                        'day' => $item->dayslot->day,
                        'start_time' => $item->dayslot->start_time ? Carbon::parse($item->dayslot->start_time)->format('g:i A') : null,
                        'end_time' => $item->dayslot->end_time ? Carbon::parse($item->dayslot->end_time)->format('g:i A') : null,
                    ];
                });
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
    public function Transcript(Request $request)
    {
        try {
            $studentId = $request->student_id;
            $sessionResults = sessionresult::with([
                'session:id,name',
                'student:id,name',
            ])
                ->where('student_id', $studentId)
                ->get()
                ->map(function ($sessionResult) {
                    $subjects = student_offered_courses::with([
                        'offeredCourse.course:id,name,code,credit_hours',
                    ])
                        ->where('student_id', $sessionResult->student_id)
                        ->whereHas('offeredCourse', function ($query) use ($sessionResult) {
                            $query->where('session_id', $sessionResult->session_id);
                        })
                        ->get()
                        ->map(function ($subject) {
                            return [
                                'course_name' => $subject->offeredCourse->course->name,
                                'course_code' => $subject->offeredCourse->course->code,
                                'credit_hours' => $subject->offeredCourse->course->credit_hours,
                                'grade' => $subject->grade ?? 'Pending',
                            ];
                        });

                    return [
                        'total_credit_points' => $sessionResult->ObtainedCreditPoints,
                        'GPA' => $sessionResult->GPA,
                        'session_name' => $sessionResult->session->name,
                        'subjects' => $subjects,
                    ];
                });
            return response()->json($sessionResults);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function Notification(Request $request)
    {
        try {
            $section_id = $request->section_id;
            $Notification = notification::with(['senderUser:id,username'])
                ->where('Student_Section', $section_id)
                ->where('reciever', 'Student')
                ->orWhere('Brodcast', 1)
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();

            $Notification = $Notification->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'url' => $item->url,
                    'notification_date' => $item->notification_date,
                    'sender' => $item->sender,
                    'TL_sender_id' => $item->senderUser->username ?? 'N/A',
                ];
            });
            return response()->json([
                'status' => 'success',
                'data' => $Notification
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getAttendance(Request $request)
    {
        try {
            $studentId = $request->student_id;
           $attendanceData=(new attendance())->getAttendanceByID($studentId);
            return response()->json([
                'message' => 'Attendance data fetched successfully',
                'data' => $attendanceData,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function submitAnswer(Request $request)
    {
        $studentId = $request->student_id;
        $taskId = $request->task_id;
        try {
            if (student_task_submission::where('Student_id', $studentId)->where('Task_id', $taskId)->first()) {
                throw new Exception('Submission Already Exsists');
            }
            $student = Student::findOrFail($studentId);
            $taskTitle = Task::findOrFail($taskId)->title;
            $session = (new session())->getCurrentSessionId();
            $sessionIs = session::findOrFail($session);
            $sessionName = $sessionIs->name;
            $sessionYear = $sessionIs->year;
            $studentRegNo = $student->RegNo;
            $task = Task::with('teacherOfferedCourse')->findOrFail($taskId);
            $teacherOfferedCourseId = $task->teacher_offered_course_id;
            $teacherOfferedCourse = teacher_offered_courses::findOrFail($teacherOfferedCourseId);
            $sectionId = $teacherOfferedCourse->section_id;
            $offeredcourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->with(['course'])->first();
            $course_name = $offeredcourse->course->name;
            $section = Section::findOrFail($sectionId);
            $taskSectionName = $section->program . '-' . $section->semester . $section->group;
            $taskTitle = $task->title;
            $fileName = "({$studentRegNo})-{$taskTitle}.pdf";
            $directoryPath = "BIIT/{$sessionName}-{$sessionYear}/{$taskSectionName}/{$course_name}/";
            $storagePath = storage_path("app/public/{$directoryPath}");
            if (!file_exists($storagePath)) {
                if (!mkdir($storagePath, 0777, true)) {
                    throw new Exception('Failed to create directory.');
                }
            }
            if ($request->hasFile('Answer') && $request->file('Answer')->isValid()) {
                // Use the public disk to store the file
                $filePath = $request->file('Answer')->storeAs($directoryPath, $fileName, 'public');

                return student_task_submission::create([
                    'Answer' => $filePath, // Store path without "public/" prefix
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
            }

            if ($request->has('Answer')) {
                // Decode base64 PDF content
                $base64Data = $request->input('Answer');
                $base64 = explode(",", $base64Data)[1];
                $pdfData = base64_decode($base64);

                // Save file using Storage facade
                $filePath = "{$directoryPath}{$fileName}";
                Storage::disk('public')->put($filePath, $pdfData);

                return student_task_submission::create([
                    'Answer' => $filePath, // Store path without "public/" prefix
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
            }
            throw new Exception('Invalid file or no file uploaded.');
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Record not found',
                'error' => $e->getMessage()
            ], 404);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'error' => $e->getMessage()
            ], 422);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while uploading the file.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    ///////////////////////////////////////////////////////////////////UNDONE/////////////////////////////////
    public function Login(Request $request)
    {
        try {
            $currentSession = (new session())->getCurrentSessionId();
            $request->validate([
                "username" => 'required|string|ends_with:@biit',
                "password" => 'required'
            ]);
            $user = User::with('role')
                ->where('username', $request->username)
                ->where('password', $request->password)
                ->firstOrFail();
            $role = $user->role->type;
            if ($role == 'Student') {
                $student = student::with(['session', 'program', 'section'])
                    ->where('user_id', $user->id)
                    ->first();
                $student_id = $student->pluck('id');
                $section_id = $student->section_id;
                return response()->json([
                    'Type' => $role,
                    'StudentInfo' => $student,
                    "timetable" =>timetable::getTodayTimetableBySectionId($section_id),
                    "Attendance" =>(new attendance())->getAttendanceByID($student_id)
                ], 200);
            } else if ($role == 'Admin') {
                $Admin=admin::where('user_id',$user->id)
                ->with(['user'])
                ->first();
                return response()->json([
                    'Type' => $role,
                    'AdminInfo'=>$Admin
                ], 200);
            } else if ($role == 'Teacher') {
                $teacher=teacher::where('user_id',$user->id)
                ->with(['user'])
                ->first();
                return response()->json([
                    'Type' => $role,
                    'TeacherInfo'=>$teacher,
                    'Timetable'=>timetable::getTodayTimetableOfTeacherById($teacher->id)??'Not Fetched'
                ], 200);
            } else if ($role == 'Datacell') {
                $datacell=datacell::where('user_id',$user->id)
                ->with(['user'])
                ->first();
                return response()->json([
                   'Type' => $role,
                   'DatacellInfo'=>$datacell
                ], 200);
            } else if ($role == 'JuniorLecturer') {
                return response()->json([
                    'status' => 'success',
                    'data' => 'You are Junior Lecturer '
                ], 200);
            }

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


    public function AcademicReport(Request $request)
    {
        try {
            $student_id = $request->student_id;
            $course_id = $request->course_id;
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function Sample(Request $request)
    {
        try {
            $id = $request->id;
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
    public function sendForgotPasswordEmail(Request $request)
    {
        $email=$request->email;
        $otp = rand(100000, 999999);
        try {
            // Send the OTP email
            Mail::raw("Your OTP code is: $otp", function ($message) use ($email) {
                $message->to($email)
                        ->subject('Your OTP Code')
                        ->from('BIIT@edu.pk.com', 'LMS'); // Replace with your email
            });
    
            return response()->json(['message' => 'OTP sent successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send email', 'error' => $e->getMessage()], 500);
        }
    }
}
