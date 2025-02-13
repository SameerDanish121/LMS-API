<?php

namespace App\Http\Controllers;

use App\Models\contested_attendance;
use App\Models\exam;
use App\Models\grader;
use App\Models\student_exam_result;
use App\Models\task_consideration;
use Illuminate\Http\Request;
use App\Models\Action;
use App\Models\admin;
use App\Models\excluded_days;
use App\Models\FileHandler;
use App\Models\juniorlecturer;
use App\Models\program;
use App\Models\quiz_questions;
use App\Models\StudentManagement;
use App\Models\subjectresult;
use App\Models\teacher_juniorlecturer;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Mail;
use App\Models\datacell;
use App\Models\teacher;
use Illuminate\Support\Facades\Validator;
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
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\select;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
class StudentsController extends Controller
{
    public function sendNotification(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string|max:1000',
                'url' => 'nullable|url|max:255',
                'sender' => 'required|in:Teacher,JuniorLecturer,Admin,Datacell',
                'reciever' => 'required|string|max:255',
                'Brodcast' => 'required|boolean',
                'Student_Section' => 'nullable|integer',
                'TL_receiver_id' => 'nullable|integer',
                'TL_sender_id' => 'nullable|integer',
            ]);
            $sender = $request->sender;
            $r= $request->reciever;
            if ($r === 'Admin' || $r === 'Datacell') {
                return response()->json(['message' => 'Cant Send Messages Between Admin and Datacell'], 400);
            }
            $data = [
                'title' => $request->title,
                'description' => $request->description,
                'url' => $request->url,
                'notification_date' => now(),
                'sender' => $sender,
                'reciever' => $request->reciever,
                'Brodcast' => $request->Brodcast,
                'Student_Section' => $request->Student_Section == 0 ? null : $request->Student_Section,
                'TL_receiver_id' => $request->TL_receiver_id == 0 ? null : $request->TL_receiver_id,
                'TL_sender_id' => $request->TL_sender_id == 0 ? null : $request->TL_sender_id,
            ];

            if ($sender === 'Teacher' || $sender === 'JuniorLecturer') {
                $data['TL_sender_id'] = $request->TL_sender_id ; // Default sender ID for teachers
            } elseif ($sender === 'Admin') {
                $data['TL_sender_id'] = $request->TL_sender_id ?? 262; // Set a valid default for Admin (if needed)
            } elseif ($sender === 'Datacell') {
                $data['TL_sender_id'] = $request->TL_sender_id ?? 263; // Set a valid default for Datacell (if needed)
            } else {
                return response()->json(['message' => 'Invalid sender role.'], 400);
            }


            $notification = notification::create($data);

            return response()->json(['message' => 'Notification sent successfully!', 'data' => $notification], 201);

        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send notification.', 'error' => $e->getMessage()], 500);
        }
    }
    public function Login(Request $request)
    {
        try {
            $request->validate([
                "username" => 'required|string',
                "password" => 'required'
            ]);
            $user = User::with('role')
                ->where('username', $request->username)
                ->where('password', $request->password)
                ->firstOrFail();
               
            $role = $user->role->type;
            if ($role == 'Student') {
                $student = student::where('user_id', $user->id)->with(['program', 'user'])
                    ->first();
                if(!$student){
                    throw new Exception('No user Found');
                }                    
                $student_id = $student->value('id');
                $section_id = $student->section_id;
                $attribute = excluded_days::checkHoliday() ? 'Holiday' : 'Timetable';
                $rescheduled = excluded_days::checkReschedule();
                if ($rescheduled) {
                    $Notice = excluded_days::checkReasonOfReschedule();
                    $attribute = 'Reschedule';
                    $timetable = timetable::getTodayTimetableOfEnrollementsByStudentId($student_id, excluded_days::checkRescheduleDay() ?? null);
                } else {
                    $timetable = timetable::getTodayTimetableOfEnrollementsByStudentId($student_id);
                }
                $studentInfo = [
                    "id" => $student->id,
                    "name" => $student->name??'N/A',
                    "RegNo" => $student->RegNo,
                    "CGPA" => $student->cgpa,
                    "Gender" => $student->gender,
                    "Guardian" => $student->guardian,
                    "username" => $student->user->username,
                    "password" => $student->user->password,
                    "email" => $student->user->email,
                    "InTake" => (new session())->getSessionNameByID($student->session_id),
                    "Program" => $student->program->name??'N/A',
                    "Is Grader ?"=> grader::where('student_id',$student->id)->exists(),
                    "Section" => (new section())->getNameByID($student->section_id),
                    "Total Enrollments" => student_offered_courses::GetCountOfTotalEnrollments($student->id),
                    "Current Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?: 'N/A',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                     "Attendance" => (new attendance())->getAttendanceByID($student_id),
                    "Image" => $student->image?asset($student->image):null
                ];
                if ($rescheduled) {
                    $studentInfo['Notice'] = $Notice;
                }
              
                return response()->json([
                    'Type' => $role,
                    'StudentInfo' => $studentInfo,
                ], 200);
            } else if ($role == 'Admin') {
                $Admin = admin::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $session = session::where('id', (new session())->getCurrentSessionId())->first();
                $admin = [
                    "id" => $Admin->id,
                    "name" => $Admin->name,
                    "phone_number" => $Admin->phone_number,
                    "Designation" => $Admin->Designation,
                    "Username" => $Admin->user->username,
                    "Password" => $Admin->user->password,
                    
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => asset($Admin->image)
                ];
                return response()->json([
                    'Type' => $role,
                    'AdminInfo' => $admin
                ], 200);
            } else if ($role == 'Teacher') {
                $teacher = teacher::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $attribute = excluded_days::checkHoliday() ? 'Holiday' : 'Timetable';
                $rescheduled = excluded_days::checkReschedule();
                if ($rescheduled) {
                    $Notice = excluded_days::checkReasonOfReschedule();
                    $attribute = 'Reschedule';
                    $timetable = timetable::getTodayTimetableOfTeacherById($teacher->id, excluded_days::checkRescheduleDay() ?? null);
                } else {
                    $timetable = timetable::getTodayTimetableOfTeacherById($teacher->id);
                }
                $Teacher = [
                    "id" => $teacher->id,
                    "name" => $teacher->name,
                    "gender" => $teacher->gender,
                    "Date Of Birth" => $teacher->date_of_birth,
                    "Username" => $teacher->user->username,
                    "Password" => $teacher->user->password,
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                    "image" => asset($teacher->image),
                ];
                if ($rescheduled) {
                    $Teacher['Notice !'] = $Notice;
                }
                return response()->json([
                    'Type' => $role,
                    'TeacherInfo' => $Teacher,
                ], 200);
            } else if ($role == 'Datacell') {
                $Datacell = datacell::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $session = session::where('id', (new session())->getCurrentSessionId())->first();
                $datacell = [
                    "id" => $Datacell->id,
                    "name" => $Datacell->name,
                    "phone_number" => $Datacell->phone_number,
                    "Designation" => $Datacell->Designation,
                    "Username" => $Datacell->user->username,
                    "Password" => $Datacell->user->password,
                   
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => asset($Datacell->image),
                ];
                return response()->json([
                    'Type' => $role,
                    'DatacellInfo' => $datacell
                ], 200);
            } else if ($role == 'JuniorLecturer') {
                $jl = juniorlecturer::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $attribute = excluded_days::checkHoliday() ? 'Holiday' : 'Timetable';
                $rescheduled = excluded_days::checkReschedule();
                if ($rescheduled) {
                    $Notice = excluded_days::checkReasonOfReschedule();
                    $attribute = 'Reschedule';
                    $timetable = timetable::getTodayTimetableOfJuniorLecturerById($jl->id, excluded_days::checkRescheduleDay() ?? null);

                } else {
                    $timetable = timetable::getTodayTimetableOfJuniorLecturerById($jl->id);
                }
                $Teacher = [
                    "id" => $jl->id,
                    "name" => $jl->name,
                    "gender" => $jl->gender,
                    "Date Of Birth" => $jl->date_of_birth,
                    "Username" => $jl->user->username,
                    "Password" => $jl->user->password,
                   
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                    "image" => asset($jl->image),
                ];
                if ($rescheduled) {
                    $Teacher['Notice !'] = $Notice;
                }
                return response()->json([
                    'Type' => $role,
                    'TeacherInfo' => $Teacher,
                ], 200);
            } else {
                return response()->json([
                    'status' => 'Failed',
                    'data' => 'You are a Unauthorized User !'
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
                'status' => 'Failed',
                'data' => 'You are a Unauthorized User !'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTranscriptPdf(Request $request)
    {
        try {
            $studentId = $request->student_id;
            $student = student::with(['program', 'section'])->find($studentId);
            $program = $student->program;
            if (!$student) {
                return response()->json(['status' => 'error', 'message' => 'Student not found'], 404);
            }

            $sessionResults = sessionresult::with([
                'session:id,name,year,start_date',
            ])
                ->where('student_id', $studentId)
                ->get()
                ->sortByDesc(function ($sessionResult) {
                    return $sessionResult->session->start_date;
                })
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
                        'session_name' => $sessionResult->session->name . '-' . $sessionResult->session->year,
                        'subjects' => $subjects,
                    ];
                });

            $pdf = Pdf::loadView('transcript', [
                'student' => $student,
                'sessionResults' => $sessionResults,
                'program' => $program,
            ])->setPaper('a4', 'portrait');

            $pdfContent = $pdf->output();

            $fileName = 'transcript_' . $student->RegNo . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
            $directory = 'storage/BIIT/temp'; // Relative to public_path()
            $fullPath = public_path($directory . '/' . $fileName);
            if (!File::exists(public_path($directory))) {
                File::makeDirectory(public_path($directory), 0777, true);
            }

            File::put($fullPath, $pdfContent);
            return asset($directory . '/' . $fileName);
            // return response()->json([
            //     'status' => 'success',
            //     'message' => 'Transcript generated successfully',
            //     'file_url' => 
            // ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function Transcript(Request $request)
    {
        try {
            $studentId = $request->student_id;
            $sessionResults = sessionresult::with([
                'session:id,name,year',
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
                        'session_name' => $sessionResult->session->name . '-' . $sessionResult->session->year,
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
    
    public function FullTimetable(Request $request)
    {
        try {
            $student_id = $request->student_id;
            $timetable = timetable::getFullTimetableOfEnrollmentsByStudentId($student_id);
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
    public function AttendancePerSubject(Request $request)
    {
        try {
            $teacher_offered_course_id = $request->teacher_offered_course_id;
            $student_id = $request->student_id;
            if (!$teacher_offered_course_id || !$student_id) {
                throw new Exception('Please Provide Values in request Properly');
            }

            $attendance = attendance::getAttendanceBySubject($teacher_offered_course_id, $student_id);
            return response()->json([
                'status' => 'Attendance Fetched Successfully',
                'data' => $attendance
            ], 200);
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
            $student_id = $request->student_id;
            $student = student::find($student_id);
            $Notification = notification::with(['senderUser:id,username'])
                ->where('Student_Section', $section_id)
                ->orWhere(
                    'TL_receiver_id',
                    $student->user_id
                )
                ->where('reciever', 'Student')
                ->orWhere('Brodcast', 1)
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();
            $Notification = $Notification->map(function ($item) {
                if ($item->sender == 'Teacher') {
                    $teacherName = teacher::where('user_id', $item->senderUser->id)->first()->name;
                } else if ($item->sender == 'JuniorLecturer') {
                    $teacherName = juniorlecturer::where('user_id', $item->senderUser->id)->first()->name;
                } else if ($item->sender == 'DataCell') {
                    $teacherName = datacell::where('user_id', $item->senderUser->id)->first()->name;
                } else if ($item->sender == 'Admin') {
                    $teacherName = admin::where('user_id', $item->senderUser->id)->first()->name;
                }
                return [
                    'sender' => $item->sender,
                    'Sender Name' => $teacherName ?? 'N/A',
                    'title' => $item->title,
                    'description' => $item->description,
                    'url' => $item->url,
                    'notification_date' => $item->notification_date,
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
            $attendanceData = (new attendance())->getAttendanceByID($studentId);
            return response()->json([
                'message' => 'Attendance data fetched successfully',
                'data' => $attendanceData,
            ], 200);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public static function calculateQuizMarks($answers, $coursecontent_id, $points)
    {
        $totalMarks = 0;
        $totalQuizMarks = 0;
        foreach ($answers as $answer) {
            $questionNo = $answer['QNo'];
            $studentAnswer = $answer['StudentAnswer'];
            $question = quiz_questions::with('Options')->where('coursecontent_id', $coursecontent_id)->where('question_no', $questionNo)->first();
            if ($question) {
                $totalQuizMarks += $question->points;
                $correctOption = $question->Options->firstWhere('is_correct', true);
                if ($correctOption && $studentAnswer === $correctOption->option_text) {
                    $totalMarks += $question->points;
                }
            }
        }
        $solidMarks = ($totalMarks / $totalQuizMarks) * $points;
        return (int) $solidMarks;
    }
    public function submitAnswer(Request $request)
    {
        $request->validate([
            'student_id' => 'required',
            'task_id' => 'required'
        ]);
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
            $task = task::with(['teacherOfferedCourse', 'courseContent'])->findOrFail($taskId);
            $teacherOfferedCourseId = $task->teacher_offered_course_id;
            $teacherOfferedCourse = teacher_offered_courses::findOrFail($teacherOfferedCourseId);
            $sectionId = $teacherOfferedCourse->section_id;
            $offeredcourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->with(['course'])->first();
            $course_name = $offeredcourse->course->description;
            $section = Section::findOrFail($sectionId);
            $taskSectionName = $section->program . '-' . $section->semester . $section->group;
            $taskTitle = $task->title;
            if ($task->courseContent->content == 'MCQS') {
                $number = self::calculateQuizMarks($request->input('Answer'), $task->courseContent->id, $task->points);
                $data = [
                    'ObtainedMarks' => $number ?? 0,
                    'Task_id' => $task->id,
                    'Student_id' => $studentId
                ];

                $result = student_task_result::updateOrInsert(
                    [
                        'Task_id' => $task->id,
                        'Student_id' => $student->id

                    ],
                    [
                        'ObtainedMarks' => $number ?? 0
                    ]
                );

                $studentCountofSectionforTask = student_offered_courses::where('section_id', $section->id)->where('offered_course_id', $offeredcourse->id)->count();
                $countofsubmission = student_task_result::where('Task_id', $task->id)->count();
                if ($studentCountofSectionforTask === $countofsubmission) {
                    task::ChangeStatusOfTask($task->id);
                }
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Obtained Marks' => $number,
                    'Total Marks of Task' => $task->points,
                    'Message After Submission' => "You Got {$number} Out of {$task->points} ! ",
                    'Quiz Data' => Action::getMCQS($task->courseContent->id),
                    'Your Submissions' => $request->Answer
                ], 200);
            }
            $fileName = "({$studentRegNo})-{$taskTitle}";
            $directoryPath = "{$sessionName}-{$sessionYear}/{$taskSectionName}/{$course_name}/Task";
            if ($request->hasFile('Answer') && $request->file('Answer')->isValid()) {
                $filePath = FileHandler::storeFile($fileName, $directoryPath, $request->file('Answer'));
                student_task_submission::create([
                    'Answer' => $filePath,
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Total Marks of Task' => $task->points,
                    "{$task->type} Data" => asset($task->courseContent->content) ?: null,
                    'Your Submissions' => asset($filePath) ?: null,

                ], 200);
            }
            if ($request->has('Answer')) {
                // $base64Data = $request->input('Answer');
                // $base64 = explode(",", $base64Data)[1];
                // $pdfData = base64_decode($base64);
                // $filePath = "{$directoryPath}{$fileName}";
                // Storage::disk('public')->put($filePath, $pdfData);
                $filePath = FileHandler::storeFileUsingContent($request->input('Answer'), $fileName, $directoryPath);
                student_task_submission::create([
                    'Answer' => $filePath,
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Total Marks of Task' => $task->points,
                    "{$task->type} Data" => asset($task->courseContent->content) ?: null,
                    'Your Submissions' => asset($filePath) ?: null,
                ], 200);
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
    public function StudentCurrentEnrollmentsName(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id = $request->student_id;
            $names = StudentManagement::getActiveEnrollmentCoursesName($id);
            return response()->json([
                'success' => true,
                'message' => $names
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
    public function StudentAllEnrollmentsName(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id = $request->student_id;
            $names = StudentManagement::getAllEnrollmentCoursesName($id);
            return response()->json([
                'success' => true,
                'message' => $names
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
    public function updateStudentImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'student_id' => 'required',
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
            $student_id = $request->student_id;
            $file = $request->file('image');
            $responseMessage = StudentManagement::updateStudentImage($student_id, $file);
            return response()->json([
                'success' => true,
                'message' => $responseMessage
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
                'newPassword' => 'required',
            ]);
            if (user::where('password', $request->newPassword)->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password is Already Taken by Differenet User ! Please Try New'
                ], 401);
            }
            $responseMessage = StudentManagement::updateStudentPassword(
                $request->student_id,
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
    public function getSubjectExamResult(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
                'offered_course_id' => 'required'
            ]);
            $studentId = $request->student_id;
            $offerId = $request->offered_course_id;
            $responseMessage = StudentManagement::getExamResultsGroupedByTypeWithStats($studentId, $offerId);
            return response()->json([
                'success' => 'Fetched Successfully!',
                'ExamDetails' => $responseMessage
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 400);
        } catch (ModelNotFoundException $e) {
            // Handle model not found exception
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid data provided'
            ], 404);
        } catch (Exception $e) {
            // Handle unexpected exceptions
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function GetTaskDetails(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $studentId = $request->student_id;
            $responseMessage = StudentManagement::getAllTask($studentId);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'TaskDetails' => $responseMessage
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
    public function GetSubjectTaskResult(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
                'offered_course_id' => 'required'
            ]);
            $studentId = $request->student_id;
            $offer_id = $request->offered_course_id;
            $responseMessage = StudentManagement::getSubmittedTasksGroupedByTypeWithStats($studentId, $offer_id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'All Task' => $responseMessage
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

    public function GetFullCourseContentOfSubject(Request $request)
    {
        try {
            $request->validate([
                'section_id' => 'required',
                'offered_course_id' => 'required'
            ]);
            $section_id = $request->section_id;
            $offered_course_id = $request->offered_course_id;
            $responseMessage = StudentManagement::getCourseContentWithTopicsAndStatus($section_id, $offered_course_id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage
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
    public function GetFullCourseContentOfSubjectByWeek(Request $request)
    {
        try {
            $request->validate([
                'section_id' => 'required',
                'offered_course_id' => 'required',
                'Week_No' => 'required|integer'
            ]);
            $section_id = $request->section_id;
            $offered_course_id = $request->offered_course_id;
            $week = $request->Week_No;
            $responseMessage = StudentManagement::getCourseContentForSpecificWeekWithTopicsAndStatus($section_id, $offered_course_id, $week);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage
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
    public function ContestAttendance(Request $request)
    {
        try {
            $request->validate([
                'attendance_id' => 'required',
            ]);
            $id = $request->attendance_id;

            $responseMessage = StudentManagement::createContestedAttendance($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage ? 'Your attendance contest has been successfully submitted and is now under review.'
                    : 'You have already submitted a contest for this attendance. It is currently pending review.'
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
    public function GetActiveEnrollments(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id = $request->student_id;
            $responseMessage = StudentManagement::getYourEnrollments($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage
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
    public function getYourPreviousEnrollments(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id = $request->student_id;
            $responseMessage = StudentManagement::getYourPreviousEnrollments($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage
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
    public function TranscriptSessionDropDown(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id = $request->student_id;
            $responseMessage = StudentManagement::getSessionIdsByStudentId($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Records' => $responseMessage
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
    public function Notifications(Request $request)
    {
        try {
            $section_id = $request->section_id;
            $student_id = $request->student_id;
            $student = student::find($student_id);

            $sectionNotifications = notification::with(['senderUser:id,username'])
                ->where('Student_Section', $section_id) // Filter by section_id
                ->where('reciever', 'Student') // Filter by recipient type (Student)
                ->whereNull('TL_receiver_id') // Ensure it's a section-wide notification (not for a specific student)
                ->where('Brodcast', 0) // Ensure the message is targeted to the section
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();

            // Fetch Direct Notifications to a specific Student (by TL_receiver_id)
            $studentNotifications = notification::with(['senderUser:id,username'])
                ->where('TL_receiver_id', $student->user_id) // Filter by TL_receiver_id (student-specific)
                ->where('reciever', 'Student') // Filter by recipient type (Student)
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();

            // Fetch Broadcast Notifications (where Brodcast = 1)
            $broadcastNotifications = notification::with(['senderUser:id,username'])
                ->where('Brodcast', 1) // Fetch notifications broadcasted to all students
                ->where('reciever', 'Student') // Filter by recipient type (Student)
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();
            $notifications = $sectionNotifications->merge($studentNotifications)->unique('id'); // Assuming 'id' is unique

            // Map all notifications (section and student-specific) to the desired format
            $notifications = $sectionNotifications->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'url' => $item->url,
                    'notification_date' => $item->notification_date,
                    'sender' => $item->sender,
                    'TL_sender_id' => $item->senderUser->username ?? 'N/A',
                ];
            });
            $broadcastNotifications = $broadcastNotifications->map(function ($item) {
                return [
                    'title' => $item->title,
                    'description' => $item->description,
                    'url' => $item->url,
                    'notification_date' => $item->notification_date,
                    'sender' => $item->sender,
                    'TL_sender_id' => $item->senderUser->username ?? 'N/A',
                ];
            });

            $studentNotifications = $studentNotifications->map(function ($item) {
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
                'data' => [
                    'section_notifications' => $notifications,
                    'Personal_notifications' => $studentNotifications,
                    'broadcast_notifications' => $broadcastNotifications
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
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
            if (!empty($teacherTasks)) {
                $messages[] = "Best {$type} tasks for Teacher are listed.";
            }
            if (!empty($juniorTasks)) {
                $messages[] = "Best {$type} tasks for JuniorLecturer are listed.";
            }
            if (empty($teacherTasks) && empty($juniorTasks)) {
                $messages[] = "No tasks found for {$type}.";
            }
        }
        return implode(" ", $messages);
    }
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
        if ($taskConsideration->isEmpty()) {
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

    public function getStudentExamResult(Request $request)
    {
        $validated = $request->validate([
            'teacher_offered_course_id' => 'required|integer',
            'student_id' => 'required|integer',
        ]);
        if(!student::find($validated['student_id'])){
            return 'No Student Found  !';
        }
        $teacherOfferedCourse=teacher_offered_courses::with(['offeredCourse'])->find($validated['teacher_offered_course_id']);
        if(!$teacherOfferedCourse){
            return 'The Teacher Allocation Found Esist !';
        }
        $offeredCourseId = $teacherOfferedCourse->offeredCourse->id;
        $studentId = $validated['student_id'];
        $exams =exam::where('offered_course_id', $offeredCourseId)->with(['questions'])->get();

        if ($exams->isEmpty()) {
            return response()->json([
                'message' => 'No exams found for the given offered course.',
            ], 404);
        }
        $results = [];
        $totalObtainedMarks = 0;
        $totalMarks = 0;
        $solidMarks = 0;
        foreach ($exams as $exam) {
            $examData = [
                'exam_id' => $exam->id,
                'exam_type' => $exam->type,
                'total_marks' => $exam->total_marks,
                'solid_marks' => $exam->Solid_marks,
                'obtained_marks'=>0,
                'solid_marks_equivalent'=>0,
                'status' => 'Declared',
                'questions' => [],
               
            ];
            $examTotalObtained = 0;
            foreach ($exam->questions as $question) {
                $result = student_exam_result::where('question_id', $question->id)
                    ->where('student_id', $studentId)
                    ->where('exam_id', $exam->id)
                    ->first();

                $questionData = [
                    'question_id' => $question->id,
                    'q_no' => $question->q_no,
                    'marks' => $question->marks,
                    'obtained_marks' => $result ? $result->obtained_marks : null,
                ];

                $examData['questions'][] = $questionData;

                if ($result) {
                    $examTotalObtained += $result->obtained_marks;
                }
            }
            $examData['obtained_marks'] = $examTotalObtained;
            $examData['solid_marks_equivalent'] =$exam->Solid_marks > 0
                ? ($examTotalObtained /  $exam->total_marks) * $exam->Solid_marks
                : 0;
            $totalObtainedMarks += $examTotalObtained;
            $totalMarks += $exam->total_marks;
            $solidMarks += $exam->Solid_marks;

            $results[$exam->type][] = $examData;
        }
        $student=student::find($studentId);
        return response()->json([
            'Student Name'=>$student->name,
            'Registration Number'=>$student->RegNo,
            'total_marks' => $totalMarks,
            'solid_marks' => $solidMarks,
            'obtained_marks' => $totalObtainedMarks,
            'solid_marks_equivalent' => $solidMarks > 0
                ? ($totalObtainedMarks / $totalMarks) * $solidMarks
                : 0,
            'exam_results' => $results,
        ]);
    }

}
