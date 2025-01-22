<?php

namespace App\Http\Controllers;
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
use Illuminate\Http\Request;
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
class StudentController extends Controller
{
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
    public function FullTimetable(Request $request)
    {
        try {
            $section_id = $request->section_id;
            $timetable = timetable::getFullTimetableBySectionId($section_id);
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
                        'session_name' => $sessionResult->session->name.'-'.$sessionResult->session->year,
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
            $student_id=$request->student_id;
            $student=student::find($student_id);
            $Notification = notification::with(['senderUser:id,username'])
                ->where('Student_Section', $section_id)
                ->orWhere('TL_receiver_id',$student->user_id
                )
                ->where('reciever', 'Student')
                ->orWhere('Brodcast', 1)
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();
            $Notification = $Notification->map(function ($item) {
                if($item->sender=='Teacher'){
                    $teacherName=teacher::where('user_id',$item->senderUser->id)->first()->name;
                }else if($item->sender=='JuniorLecturer'){
                    $teacherName=juniorlecturer::where('user_id',$item->senderUser->id)->first()->name;
                }else if($item->sender=='DataCell'){
                    $teacherName=datacell::where('user_id',$item->senderUser->id)->first()->name;
                }else if($item->sender=='Admin'){
                    $teacherName=admin::where('user_id',$item->senderUser->id)->first()->name;
                }
                return [
                    'sender' => $item->sender,
                    'Sender Name'=>$teacherName??'N/A',
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
            $task = task::with(['teacherOfferedCourse','courseContent'])->findOrFail($taskId);
            $teacherOfferedCourseId = $task->teacher_offered_course_id;
            $teacherOfferedCourse = teacher_offered_courses::findOrFail($teacherOfferedCourseId);
            $sectionId = $teacherOfferedCourse->section_id;
            $offeredcourse = offered_courses::where('id', $teacherOfferedCourse->offered_course_id)->with(['course'])->first();
            $course_name = $offeredcourse->course->name;
            $section = Section::findOrFail($sectionId);
            $taskSectionName = $section->program . '-' . $section->semester . $section->group;
            $taskTitle = $task->title;
            if($task->courseContent->content=='MCQS'){
                 $number=self::calculateQuizMarks($request->input('Answer'),$task->courseContent->id,$task->points);
                 $data = [
                    'ObtainedMarks' => $number??0, 
                    'Task_id' => $task->id,      
                    'Student_id' => $studentId
                ];
                
                $result = student_task_result::updateOrInsert(
                    [
                        'Task_id' => $task->id,
                        'Student_id' =>$student->id
                        
                    ],[
                        'ObtainedMarks' => $number??0
                    ]
                );
               
                $studentCountofSectionforTask=student_offered_courses::where('section_id',$section->id)->where('offered_course_id',$offeredcourse->id)->count();
                $countofsubmission=student_task_result::where('Task_id',$task->id)->count();
                if($studentCountofSectionforTask===$countofsubmission){
                    task::ChangeStatusOfTask($task->id);
                }
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Obtained Marks' =>$number,
                    'Total Marks of Task'=>$task->points,
                    'Message After Submission'=>"You Got {$number} Out of {$task->points} ! ",
                    'Quiz Data'=>Action::getMCQS($task->courseContent->id),
                    'Your Submissions'=>$request->Answer
                ], 200);
            }
            $fileName = "({$studentRegNo})-{$taskTitle}";
            $directoryPath = "{$sessionName}-{$sessionYear}/{$taskSectionName}/{$course_name}/Task";
            if ($request->hasFile('Answer') && $request->file('Answer')->isValid()) {
                $filePath = FileHandler::storeFile($fileName,$directoryPath,$request->file('Answer'));
                student_task_submission::create([
                    'Answer' => $filePath, 
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Total Marks of Task'=>$task->points,
                    "{$task->type} Data"=>FileHandler::getFileByPath($task->courseContent->content),
                    'Your Submissions'=>FileHandler::getFileByPath($filePath),

                ], 200);
            }
            if ($request->has('Answer')) {
                // $base64Data = $request->input('Answer');
                // $base64 = explode(",", $base64Data)[1];
                // $pdfData = base64_decode($base64);
                // $filePath = "{$directoryPath}{$fileName}";
                // Storage::disk('public')->put($filePath, $pdfData);
                $filePath=FileHandler::storeFileUsingContent( $request->input('Answer'),$fileName,$directoryPath);
                student_task_submission::create([
                    'Answer' => $filePath,
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
                return response()->json([
                    'message' => 'Your Submission Has been Added !',
                    'Total Marks of Task'=>$task->points,
                    "{$task->type} Data"=>FileHandler::getFileByPath($task->courseContent->id),
                    'Your Submissions'=>FileHandler::getFileByPath($filePath),
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
    ///////////////////////////////////////////////////////////////////UNDONE/////////////////////////////////
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
                $student_id = $student->value('id');
                $section_id = $student->section_id;
                $attribute = excluded_days::checkHoliday() ? 'Holiday' : 'Timetable';
                $studentInfo = [
                    "id" => $student->id,
                    "name" => $student->name,
                    "RegNo" => $student->RegNo,
                    "CGPA" => $student->cgpa,
                    "Gender" => $student->gender,
                    "Guardian" => $student->guardian,
                    "username" => $student->user->username,
                    "password" => $student->user->password,
                    "email" => $student->user->email,
                    "InTake" => (new session())->getSessionNameByID($student->session_id),
                    "Program" => $student->program->name,
                    "Section" => (new section())->getNameByID($student->section_id),
                    "Total Enrollments" => student_offered_courses::GetCountOfTotalEnrollments($student->id),
                    "Current Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?: 'N/A',
                    "Image" => Action::getImageByPath($student->image),
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : timetable::getTodayTimetableBySectionId($section_id),
                    "Attendance" => (new attendance())->getAttendanceByID($student_id)
                ];
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
                    "image" => Action::getImageByPath($Admin->image),
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A"
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
                $Teacher = [
                    "id" => $teacher->id,
                    "name" => $teacher->name,
                    "gender" => $teacher->gender,
                    "Date Of Birth" => $teacher->date_of_birth,
                    "Username" => $teacher->user->username,
                    "Password" => $teacher->user->password,
                    "image" => Action::getImageByPath($teacher->image),
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : timetable::getTodayTimetableOfTeacherById($teacher->id),
                ];
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
                    "image" => Action::getImageByPath($Datacell->image),
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A"
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
                $Teacher = [
                    "id" => $jl->id,
                    "name" => $jl->name,
                    "gender" => $jl->gender,
                    "Date Of Birth" => $jl->date_of_birth,
                    "Username" => $jl->user->username,
                    "Password" => $jl->user->password,
                    "image" => Action::getImageByPath(originalPath:$jl->image),
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : timetable::getTodayTimetableOfJuniorLecturerById($jl->id),
                ];
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
    public function StudentCurrentEnrollmentsName(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id= $request->student_id;
            $names=StudentManagement::getActiveEnrollmentCoursesName($id);
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
            $id= $request->student_id;
            $names=StudentManagement::getAllEnrollmentCoursesName($id);
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
            $responseMessage =StudentManagement::updateStudentImage($student_id, $file);
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
    public function getSubjectExamResult(Request $request) {
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
    public function GetTaskDetails(Request $request){
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $studentId=$request->student_id;
            $responseMessage =StudentManagement::getAllTask($studentId);
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
    public function GetSubjectTaskResult(Request $request){
        try {
            $request->validate([
                'student_id' => 'required',
                'offered_course_id'=>'required'
            ]);
            $studentId=$request->student_id;
            $offer_id=$request->offered_course_id;
            $responseMessage =StudentManagement:: getSubmittedTasksGroupedByTypeWithStats($studentId,$offer_id);
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

    public function GetFullCourseContentOfSubject(Request $request){
        try {
            $request->validate([
                'section_id' => 'required',
                'offered_course_id'=>'required'
            ]);
            $section_id=$request->section_id;
            $offered_course_id=$request->offered_course_id;
            $responseMessage =StudentManagement::getCourseContentWithTopicsAndStatus($section_id,$offered_course_id);
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
    public function GetFullCourseContentOfSubjectByWeek(Request $request){
        try {
            $request->validate([
                'section_id' => 'required',
                'offered_course_id'=>'required',
                'Week_No'=>'required|integer'
            ]);
            $section_id=$request->section_id;
            $offered_course_id=$request->offered_course_id;
            $week=$request->Week_No;
            $responseMessage =StudentManagement::getCourseContentForSpecificWeekWithTopicsAndStatus($section_id,$offered_course_id,$week);
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
    public function ContestAttendance(Request $request){
        try {
            $request->validate([
                'attendance_id' => 'required',
            ]);
            $id=$request->attendance_id;
            $responseMessage =StudentManagement::createContestedAttendance($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'Course Content' => $responseMessage?'Your attendance contest has been successfully submitted and is now under review.'
                :'You have already submitted a contest for this attendance. It is currently pending review.'
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
    public function GetActiveEnrollments(Request $request){
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id=$request->student_id;
            $responseMessage =StudentManagement::getYourEnrollments($id);
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
    public function getYourPreviousEnrollments(Request $request){
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id=$request->student_id;
            $responseMessage =StudentManagement::getYourPreviousEnrollments($id);
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
    public function TranscriptSessionDropDown(Request $request){
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $id=$request->student_id;
            $responseMessage =StudentManagement:: getSessionIdsByStudentId($id);
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
            $student=student::find($student_id);
          
            $sectionNotifications = notification::with(['senderUser:id,username'])
                ->where('Student_Section', $section_id) // Filter by section_id
                ->where('reciever', 'Student') // Filter by recipient type (Student)
                ->whereNull('TL_receiver_id') // Ensure it's a section-wide notification (not for a specific student)
                ->where('Brodcast', 0) // Ensure the message is targeted to the section
                ->select('title', 'description', 'url', 'notification_date', 'sender', 'TL_sender_id')
                ->get();
    
            // Fetch Direct Notifications to a specific Student (by TL_receiver_id)
            $studentNotifications = notification::with(['senderUser:id,username'])
                ->where('TL_receiver_id',   $student->user_id) // Filter by TL_receiver_id (student-specific)
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
           
            $studentNotifications =  $studentNotifications->map(function ($item) {
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
    ////////////////////////////////////////////////////EXTRASSSSSSSSSSSSSSSSSSSS///////////////////
    public function sendForgotPasswordEmail(Request $request)
    {
        $email = $request->email;
        $otp = rand(100000, 999999);
        try {
            // Send the OTP email
            Mail::raw("Your OTP code is: $otp", function ($message) use ($email) {
                $message->to($email)
                    ->subject('Your OTP Code')
                    ->from('BIIT@edu.pk.com', 'LMS'); // Replace with your email
            });

            return response()->json(['message' => 'OTP sent successfully']);
        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send email', 'error' => $e->getMessage()], 500);
        }
    }
}
