<?php
namespace App\Http\Controllers;
use App\Models\contested_attendance;
use App\Models\coursecontent;
use App\Models\coursecontent_topic;
use App\Models\Director;
use App\Models\exam;
use App\Models\grader;
use App\Models\Hod;
use App\Models\student_exam_result;
use App\Models\t_coursecontent_topic_status;
use App\Models\task_consideration;
use App\Models\topic;
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
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Intervention\Image\ImageManager;
class StudentsController extends Controller
{

    public function getCelendarData(Request $request){
            $sessions = Session::orderBy('start_date', 'desc')->get();
    
            $formattedSessions = $sessions->map(function ($session) {
                return [
                    'start_date'=>$session->start_date,
                    'end_date'=>$session->end_date,
                    'name'=>(new Session())->getSessionNameByID($session->id)
                ];
            });
            $excluded_days= $sessions = excluded_days ::orderBy('date', 'desc')->get();
    
            $formattedExculded = $excluded_days->map(function ($session) {
                return [
                    'date'=>$session->date,
                    'type'=>$session->type,
                    'Reason'=>$session->reason
                ];
            });
            return response()->json([
                'session_data' => $formattedSessions,
                'exculded_days' => $formattedExculded
            ], 200);
    }
    public function OnlyConsidered($task, $settings)
    {
        $tasksCollection = collect($task);
        $finalResult = [];
    
        foreach ($settings as $type => $creatorLimits) {
            $typeResult = [];
            if(($creatorLimits['Junior Lecturer']??null)==null){
                $filtered = $tasksCollection
                ->where('type', $type)
                ->sortByDesc('obtained_points')
                ->take($creatorLimits['Total'])
                ->values(); // Reindex
               $typeResult['Teacher'] = $filtered;
            }else{
                foreach ($creatorLimits as $creator => $limit) {
                    $filtered = $tasksCollection
                        ->where('type', $type)
                        ->where('created_by', $creator)
                        ->sortByDesc('obtained_points')
                        ->take($limit)
                        ->values(); // Reindex
        
                    $typeResult[$creator] = $filtered;
                }
            }
            
    
            $finalResult[$type] = $typeResult;
        }
    
        return $finalResult;
    }    
    public function getAllTaskConsidered(Request $request)
    {
        try {
            $student_id = $request->student_id;
            $enrolledCourses = StudentManagement::getActiveEnrollmentCoursesName($student_id);
            $AllTask = [];
            foreach ($enrolledCourses as $enrollment) {

                $sectionId = $enrollment['section_id'];
                $offeredCourseId = $enrollment['offered_course_id'];
                $teacherOfferedCourse = teacher_offered_courses::where('section_id', $sectionId)
                    ->where('offered_course_id', $offeredCourseId)
                    ->first();
                if ($teacherOfferedCourse) {
                    $data = [];
                    $teacherOfferedCourseId = $teacherOfferedCourse->id;
                    $tasks = task::with(['courseContent', 'teacherOfferedCourse.section', 'teacherOfferedCourse.offeredCourse.course'])->where('teacher_offered_course_id', $teacherOfferedCourseId)->get();
                    foreach ($tasks as $task) {
                        $currentDate = now();
                        $taskDetails = [
                            'id' => $task->id,
                            'title' => $task->title,
                            'type' => $task->type,
                            'points' => $task->points,
                            'start_date' => $task->start_date,
                            'due_date' => $task->due_date,
                            'created_by' => $task->CreatedBy
                        ];
                        if ($task->start_date <= $currentDate && $task->due_date >= $currentDate) {
                            $studentTaskResult = student_task_result::where('Task_id', $task->id)
                                ->where('Student_id', $student_id)
                                ->first();
                            if ($studentTaskResult && $task->courseContent->content == 'MCQS') {
                                $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                                $data[] = $taskDetails;
                            }
                        } else {
                            $studentTaskResult = student_task_result::where('Task_id', $task->id)
                                ->where('Student_id', $student_id)
                                ->first();
                            if ($studentTaskResult) {
                                $taskDetails['obtained_points'] = $studentTaskResult->ObtainedMarks ?? null;
                                $data[] = $taskDetails;
                            }
                        }
                    }
                    usort($data, function ($a, $b) {
                        return strtotime($b['due_date']) <=> strtotime($a['due_date']);
                    });
                    $summary=$enrollment['teacher_offered_course_id'] ? task_consideration::getConsiderationSummary($enrollment['teacher_offered_course_id'], $enrollment['isLab']) : [];
                    
                    $courseBreakDown = [
                        'teacher_offered_course_id' => $enrollment['teacher_offered_course_id'],
                        'course_name' => $enrollment['course_name'],
                        'section' => $enrollment['Section'],
                        'IsLab' => $enrollment['isLab'],
                        'Consideration_Summary' =>$summary ,
                        'Tasks' => $data,
                        'Considered'=>self::OnlyConsidered($data,$summary),
                    ];
                    $AllTask[] = $courseBreakDown;
                }
            }
            return response()->json([
                'status' => 'success',
                'data' => $AllTask
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }

    }
    private function compressVideo($filePath)
    {
        $compressedFile = str_replace('.', '_compressed.', $filePath);
        shell_exec("ffmpeg -i $filePath -vcodec h264 -b:v 800k -acodec aac -b:a 128k $compressedFile");

        if (file_exists($compressedFile)) {
            unlink($filePath);
            rename($compressedFile, $filePath);
        }
    }

    private function compressPDF($filePath)
    {
        $compressedFile = str_replace('.pdf', '_compressed.pdf', $filePath);
        shell_exec("gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -dPDFSETTINGS=/screen -q -o $compressedFile $filePath");

        if (file_exists($compressedFile)) {
            unlink($filePath); // Delete old file
            rename($compressedFile, $filePath); // Replace with compressed file
        }
    }

    // private function compressImage($filePath)
    // {

    //     $image = Image::make($filePath);
    //     $image->encode('webp', 75); // Convert to WebP with 75% quality
    //     $image->save($filePath);
    // }

    public function compressFolder(Request $request)
    {
        try {
            // Get the folder path from request
            $relativePath = $request->input('folder_path');

            if (!$relativePath) {
                throw new Exception("Folder path is required.");
            }

            $folderPath = public_path($relativePath);

            // Check if the folder exists
            if (!File::exists($folderPath)) {
                throw new Exception("The specified folder does not exist.");
            }

            // Get folder size before compression
            $sizeBefore = $this->calculateFolderSize($folderPath);

            // Get all files inside the folder (including subfolders)
            $allFiles = File::allFiles($folderPath);
            $errorFiles = [];
            $compressedCount = 0;

            foreach ($allFiles as $file) {
                try {
                    $filePath = $file->getRealPath();
                    $extension = strtolower($file->getExtension());

                    if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        // $this->compressImage($filePath);
                    } elseif ($extension === 'pdf') {
                        $this->compressPDF($filePath);
                    } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'mkv'])) {
                        $this->compressVideo($filePath);
                    }

                    $compressedCount++;
                } catch (Exception $e) {
                    $errorFiles[] = [
                        'file' => $file->getFilename(),
                        'error' => $e->getMessage(),
                    ];
                }
            }

            // Get folder size after compression
            $sizeAfter = $this->calculateFolderSize($folderPath);

            // Calculate difference in size
            $sizeReduced = $sizeBefore - $sizeAfter;

            return response()->json([
                'message' => 'Folder Files Compressed Successfully!',
                'folder_path' => $relativePath,
                'total_files' => count($allFiles),
                'compressed_files' => $compressedCount,
                'size_before' => $this->formatSizes($sizeBefore),
                'size_after' => $this->formatSizes($sizeAfter),
                'size_reduced' => $this->formatSizes($sizeReduced),
                'errors' => $errorFiles,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    private function calculateFolderSize($folderPath)
    {
        $size = 0;
        if (!File::exists($folderPath)) {
            return 0;
        }
        $files = File::allFiles($folderPath);
        foreach ($files as $file) {
            $size += File::size($file);
        }
        return $size;
    }
    private function formatSize($sizeInBytes)
    {
        if ($sizeInBytes >= (1024 ** 3)) {
            return round($sizeInBytes / (1024 ** 3), 2) . ' GB';
        } elseif ($sizeInBytes >= (1024 ** 2)) {
            return round($sizeInBytes / (1024 ** 2), 2) . ' MB';
        } else {
            return round($sizeInBytes / 1024, 2) . ' KB';
        }
    }
    private function formatSizes($size)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }
        return round($size, 2) . ' ' . $units[$i];
    }

    public function getBIITFolderInfo()
    {
        try {
            $basePath = 'storage/BIIT';
            $biitPath = public_path($basePath);
            if (!File::exists($biitPath)) {
                throw new Exception("The BIIT folder does not exist.");
            }
            $totalSize = $this->calculateFolderSize($biitPath);
            $formattedTotalSize = $this->formatSize($totalSize);
            $subFolders = File::directories($biitPath);
            $folderDetails = [];
            foreach ($subFolders as $subFolder) {
                $folderName = basename($subFolder);
                $folderSize = $this->calculateFolderSize($subFolder);
                $formattedSize = $this->formatSize($folderSize);
                $relativePath = str_replace(public_path(), '', $subFolder);
                $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);
                $relativePath = str_replace('\\', '/', $relativePath);
                $folderDetails[] = [
                    'folder_name' => $folderName,
                    'path' => $relativePath,
                    'size' => $formattedSize,
                ];
            }
            return response()->json([
                'message' => 'BIIT Folder Details Fetched Successfully!',
                'main_folder' => 'BIIT',
                'path' => $basePath,
                'total_size' => $formattedTotalSize,
                'details' => $folderDetails,
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
    public function cleanTranscriptFolder()
    {
        try {
            $basePath = 'storage/BIIT/Transcript';
            $transcriptPath = public_path($basePath);

            if (!File::exists($transcriptPath)) {
                throw new Exception("The Transcript folder does not exist.");
            }

            $files = File::files($transcriptPath);
            $directories = File::directories($transcriptPath);

            // Delete all files inside Transcript folder
            foreach ($files as $file) {
                File::delete($file);
            }

            // Delete all subdirectories inside Transcript folder
            foreach ($directories as $directory) {
                File::deleteDirectory($directory);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Transcript folder cleaned successfully!'
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
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
            $r = $request->reciever;
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
                $data['TL_sender_id'] = $request->TL_sender_id; // Default sender ID for teachers
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
    public static function getOngoingTask($student_id)
    {
        return [];
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
                if (!$student) {
                    throw new Exception('No user Found');
                }
                $student_id = $student->value('id');
                $section_id = $student->section_id;
                $attribute = excluded_days::checkHoliday() ? 'Timetable' : 'Timetable';
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
                    "name" => $student->name ?? 'N/A',
                    "RegNo" => $student->RegNo,
                    "CGPA" => $student->cgpa,
                    "user_id" => $student->user->id,
                    "Gender" => $student->gender,
                    "Guardian" => $student->guardian,
                    "username" => $student->user->username,
                    "password" => $student->user->password,
                    "email" => $student->user->email,
                    "InTake" => (new session())->getSessionNameByID($student->session_id),
                    "Program" => $student->program->name ?? 'N/A',
                    "Is Grader ?" => grader::where('student_id', $student->id)->exists(),
                    "Section" => (new section())->getNameByID($student->section_id),
                    "Total Enrollments" => student_offered_courses::GetCountOfTotalEnrollments($student->id),
                    "Current Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?: 'N/A',
                    $attribute => excluded_days::checkHoliday() ? [] : $timetable,
                    "Attendance" => (new attendance())->getAttendanceByID($student_id),
                    "Image" => $student->image ? asset($student->image) : null,
                    "Current_Week" => (new session())->getCurrentSessionWeek() ?? 0,
                    "Task_Info" => self::getOngoingTask($student_id),
                ];
                if ($rescheduled) {
                    $studentInfo['Notice'] = $Notice;
                }else if(excluded_days::checkHoliday()){
                    $studentInfo['Notice']=excluded_days::checkHolidayReason();
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
                if ($Admin->user->email) {
                    AuthenticationController::sendTwoStepVer($Admin->user->id, $Admin->user->email, $Admin->name);
                }
                $admin = [
                    "id" => $Admin->id,
                    "name" => $Admin->name,
                    "phone_number" => $Admin->phone_number,
                    "Designation" => $Admin->Designation,
                    "Username" => $Admin->user->username,
                    "Password" => $Admin->user->password,
                    "user_id" => $Admin->user->id,
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => $Admin->image ? asset($Admin->image) : null,
                    "course_count" => course::count(),
                    "offered_course_count" => offered_courses::where('session_id', (new session())->getCurrentSessionId())->count(),
                    "student_count" => student::count(),
                    "faculty_count" => teacher::count() + juniorlecturer::count(),
                ];
                return response()->json([
                    'Type' => $role,
                    'AdminInfo' => $admin
                ], 200);
            } else if ($role == 'Teacher') {
                $teacher = teacher::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $attribute = excluded_days::checkHoliday() ? 'Timetable' : 'Timetable';
                $rescheduled = excluded_days::checkReschedule();
                if ($rescheduled) {
                    $Notice = excluded_days::checkReasonOfReschedule();
                    $attribute = 'Reschedule';
                    $timetable = timetable::getTodayTimetableOfTeacherById($teacher->id, excluded_days::checkRescheduleDay() ?? null);
                } else {
                    $timetable = timetable::getTodayTimetableOfTeacherById($teacher->id);
                }
                if ($teacher->user->email) {
                    AuthenticationController::sendTwoStepVer($teacher->user->id, $teacher->user->email, $teacher->name);
                }
                $Teacher = [
                    "id" => $teacher->id,
                    "name" => $teacher->name,
                    'user_id' => $teacher->user->id,
                    "gender" => $teacher->gender,
                    "Date Of Birth" => $teacher->date_of_birth,
                    "Username" => $teacher->user->username,
                    "Password" => $teacher->user->password,
                    "email" => $teacher->user->email ?? null,
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? [] : $timetable,
                    "image" => $teacher->image ? asset($teacher->image) : null,
                    "week" => (new session())->getCurrentSessionWeek() ?? 0
                ];
                if ($rescheduled) {
                    $Teacher['Notice'] = $Notice;
                }else if(excluded_days::checkHoliday()){
                    $Teacher['Notice']=excluded_days::checkHolidayReason();
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
                if ($Datacell->user->email) {
                    AuthenticationController::sendTwoStepVer($Datacell->user->id, $Datacell->user->email, $Datacell->name);
                }
                $datacell = [
                    "id" => $Datacell->id,
                    "name" => $Datacell->name,
                    "phone_number" => $Datacell->phone_number,
                    "Designation" => $Datacell->Designation,
                    "Username" => $Datacell->user->username,
                    "Password" => $Datacell->user->password,
                    "user_id" => $Datacell->user->id,
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => $Datacell->image ? asset($Datacell->image) : null,
                    "course_count" => course::count(),
                    "offered_course_count" => offered_courses::where('session_id', (new session())->getCurrentSessionId())->count(),
                    "student_count" => student::count(),
                    "faculty_count" => teacher::count() + juniorlecturer::count(),
                ];
                return response()->json([
                    'Type' => $role,
                    'DatacellInfo' => $datacell
                ], 200);
            } else if ($role == 'JuniorLecturer') {
                $jl = juniorlecturer::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $attribute = excluded_days::checkHoliday() ? 'Timetable' : 'Timetable';
                $rescheduled = excluded_days::checkReschedule();
                if ($rescheduled) {
                    $Notice = excluded_days::checkReasonOfReschedule();
                    $attribute = 'Reschedule';
                    $timetable = timetable::getTodayTimetableOfJuniorLecturerById($jl->id, excluded_days::checkRescheduleDay() ?? null);

                } else {
                    $timetable = timetable::getTodayTimetableOfJuniorLecturerById($jl->id);
                }
                if ($jl->user->email) {
                    AuthenticationController::sendTwoStepVer($jl->user->id, $jl->user->email, $jl->name);
                }
                $Teacher = [
                    "id" => $jl->id,
                    "name" => $jl->name,
                    'user_id' => $jl->user->id,
                    "gender" => $jl->gender,
                    "Date Of Birth" => $jl->date_of_birth,
                    "Username" => $jl->user->username,
                    "Password" => $jl->user->password,
                    "email" => $teacher->user->email ?? null,
                    "week" => (new session())->getCurrentSessionWeek() ?? 0,
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? []: $timetable,
                    "image" => $jl->image ? asset($jl->image) : null,
                ];
                if ($rescheduled) {
                    $Teacher['Notice'] = $Notice;
                }else if(excluded_days::checkHoliday()){
                    $studentInfo['Notice']=excluded_days::checkHolidayReason();
                }
                return response()->json([
                    'Type' => $role,
                    'TeacherInfo' => $Teacher,
                ], 200);
            } else if ($role == 'HOD') {
                $HOD = Hod::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                $session = session::where('id', (new session())->getCurrentSessionId())->first();
                if ($HOD->user->email) {
                    AuthenticationController::sendTwoStepVer($HOD->user->id, $HOD->user->email, $HOD->name);
                }
                $admin = [
                    "id" => $HOD->id,
                    "name" => $HOD->name,
                    "Designation" => $HOD->designation,
                    "Department" => $HOD->department,
                    "Username" => $HOD->user->username,
                    "Password" => $HOD->user->password,
                    "user_id" => $HOD->user->id,
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => $HOD->image ? asset($HOD->image) : null,
                    "course_count" => course::count(),
                    "offered_course_count" => offered_courses::where('session_id', (new session())->getCurrentSessionId())->count(),
                    "student_count" => student::count(),
                    "faculty_count" => teacher::count() + juniorlecturer::count(),
                    "Current_Week" => (new session())->getCurrentSessionWeek() ?? 0,
                ];
                return response()->json([
                    'Type' => $role,
                    'HODInfo' => $admin
                ], 200);
            } else if ($role == 'Director') {
                $HOD = Director::where('user_id', $user->id)
                    ->with(['user'])
                    ->first();
                if ($HOD->user->email) {
                    AuthenticationController::sendTwoStepVer($HOD->user->id, $HOD->user->email, $HOD->name);
                }
                $session = session::where('id', (new session())->getCurrentSessionId())->first();
                $admin = [
                    "id" => $HOD->id,
                    "name" => $HOD->name,
                    "Designation" => $HOD->designation,
                    "Username" => $HOD->user->username,
                    "Password" => $HOD->user->password,
                    "user_id" => $HOD->user->id,
                   "Current_Week" => (new session())->getCurrentSessionWeek() ?? 0,
                    "Current Session" => (new session())->getSessionNameByID($session->id) ?? 'N/A',
                    "Start Date" => $session->start_date ?? "N/A",
                    "End Date" => $session->end_date ?? "N/A",
                    "image" => $HOD->image ? asset($HOD->image) : null,
                    "course_count" => course::count(),
                    "offered_course_count" => offered_courses::where('session_id', (new session())->getCurrentSessionId())->count(),
                    "student_count" => student::count(),
                    "faculty_count" => teacher::count() + juniorlecturer::count(),
                ];
                return response()->json([
                    'Type' => $role,
                    'DirectorInfo' => $admin
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
    public function RememberMe(Request $request)
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
                if (!$student) {
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
                    "name" => $student->name ?? 'N/A',
                    "RegNo" => $student->RegNo,
                    "CGPA" => $student->cgpa,
                    "user_id" => $student->user->id,
                    "Gender" => $student->gender,
                    "Guardian" => $student->guardian,
                    "username" => $student->user->username,
                    "password" => $student->user->password,
                    "email" => $student->user->email,
                    "InTake" => (new session())->getSessionNameByID($student->session_id),
                    "Program" => $student->program->name ?? 'N/A',
                    "Is Grader ?" => grader::where('student_id', $student->id)->exists(),
                    "Section" => (new section())->getNameByID($student->section_id),
                    "Total Enrollments" => student_offered_courses::GetCountOfTotalEnrollments($student->id),
                    "Current Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?: 'N/A',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                    "Attendance" => (new attendance())->getAttendanceByID($student_id),
                    "Image" => $student->image ? asset($student->image) : null,
                    "Current_Week" => (new session())->getCurrentSessionWeek() ?? 0,
                    "Task_Info" => self::getOngoingTask($student_id),
                ];
                if ($rescheduled) {
                    $studentInfo['Notice'] = $Notice;
                }
                return response()->json([
                    'Type' => $role,
                    'StudentInfo' => $studentInfo,
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
                    'user_id' => $teacher->user->id,
                    "gender" => $teacher->gender,
                    "Date Of Birth" => $teacher->date_of_birth,
                    "Username" => $teacher->user->username,
                    "email" => $teacher->user->email ?? null,
                    "Password" => $teacher->user->password,
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                    "image" => $teacher->image ? asset($teacher->image) : null,
                    "week" => (new session())->getCurrentSessionWeek() ?? 0
                ];
                if ($rescheduled) {
                    $Teacher['Notice !'] = $Notice;
                }
                return response()->json([
                    'Type' => $role,
                    'TeacherInfo' => $Teacher,
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
                    'user_id' => $jl->user->id,
                    "gender" => $jl->gender,
                    "Date Of Birth" => $jl->date_of_birth,
                    "Username" => $jl->user->username,
                    "Password" => $jl->user->password,
                    "email" => $teacher->user->email ?? null,
                    "Session" => (new session())->getSessionNameByID((new session())->getCurrentSessionId()) ?? 'No Session is Active',
                    $attribute => excluded_days::checkHoliday() ? excluded_days::checkHolidayReason() : $timetable,
                    "image" => $jl->image ? asset($jl->image) : null,
                    "week" => (new session())->getCurrentSessionWeek() ?? 0
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
    public function ViewTranscript(Request $request)
    {
        try {
            $studentId = $request->query('student_id');
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
            return response()->json([
                'status' => 'success',
                'message' => 'Transcript generated successfully',
                'student' => $student,
                'sessionResults' => $sessionResults,
                'program' => $program,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
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
            $currentSessionId = (new session())->getCurrentSessionId();
            $sessionResults = sessionresult::with([
                'session:id,name,year,start_date',
            ])->where('session_id', '!=', $currentSessionId)
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
            $directory = 'storage/BIIT/Transcript'; // Relative to public_path()
            $fullPath = public_path($directory . '/' . $fileName);
            if (!File::exists(public_path($directory))) {
                File::makeDirectory(public_path($directory), 0777, true);
            }

            File::put($fullPath, $pdfContent);
            // return asset($directory . '/' . $fileName);
            return response()->streamDownload(
                fn() => print ($pdf->output()),
                "Transcript_{$student->RegNo}.pdf",
                ["Content-Type" => "application/pdf"]
            );
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
        $currentSessionId = (new session())->getCurrentSessionId();

        try {
            $studentId = $request->student_id;
            $sessionResults = sessionresult::with([
                'session:id,name,year,start_date',
                'student:id,name',
            ])->where('session_id', '!=', $currentSessionId)
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
                                'overall' => subjectresult::where('student_offered_course_id', $subject->id)->first() ?? 'N/A',
                            ];
                        });
                    return [
                        'session_start' => $sessionResult->session->start_date,
                        'total_credit_points' => $sessionResult->ObtainedCreditPoints,
                        'GPA' => $sessionResult->GPA,
                        'session_name' => $sessionResult->session->name . '-' . $sessionResult->session->year,
                        'subjects' => $subjects,

                    ];
                })->sortByDesc(function ($item) {
                    return strtotime($item['session_start']);
                })
                ->values();
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
    public function PingNotification(Request $request)
    {
        try {
            $student_id = $request->student_id;
            $last_fetch_time = $request->last_fetch_time; // <-- add this
    
            $student = student::find($student_id);
            if (!$student) {
                return response()->json(['status' => 'error', 'message' => 'Student not found.'], 404);
            }
    
            $user_id = $student->user_id;
    
            $sectionList = [$student->section_id];
            $currentSessionId = (new session())->getCurrentSessionId();
            if ($currentSessionId) {
                $enrollments = student_offered_courses::with(['offeredCourse:id,course_id'])
                    ->where('student_id', $student_id)
                    ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                        $query->where('session_id', $currentSessionId);
                    })
                    ->get();
                $additionalSections = $enrollments->pluck('section_id')->unique()->toArray();
                $sectionList = array_unique(array_merge($sectionList, $additionalSections));
            }
    
            // Filter all notifications by notification_date > last_fetch_time
            $filter = function($query) use ($last_fetch_time) {
                if ($last_fetch_time) {
                    $query->where('notification_date', '>', $last_fetch_time);
                }
            };
    
            $broadcastAll = notification::where('Brodcast', 1)->whereNull('reciever')->where($filter);
            $broadcastToStudents = notification::where('Brodcast', 1)->where('reciever', 'Student')->where($filter);
            $directToUser = notification::where('TL_receiver_id', $user_id)->where($filter);
            $sectionNotifications = notification::whereIn('Student_Section', $sectionList)->where($filter);
    
            $notifications = $broadcastAll->get()
                ->merge($broadcastToStudents->get())
                ->merge($directToUser->get())
                ->merge($sectionNotifications->get());
    
            $sortedNotifications = $notifications->unique('id')->sortByDesc('notification_date')->values();
            $finalResponse = $sortedNotifications->map(function ($note) {
                $senderName = 'System';
                $image = null;
                $type = null;
                $imageOrLink = null;

                if ($note->sender === 'Admin') {
                    $admin = admin::where('user_id', $note->TL_sender_id)->first();
                    if ($admin) {
                        $senderName = $admin->name ?? 'N/A';
                        $image = $admin->image ? asset($admin->image) : null;
                    }
                } else if ($note->sender === 'DataCell') {
                    $datacell = datacell::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender === 'JuniorLecturer') {
                    $datacell = juniorlecturer::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender === 'Teacher') {
                    $datacell = Teacher::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender == 'Director') {
                    $datacell = Director::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender == 'HOD') {
                    $datacell = Hod::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
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
                    'media_type' => $type,
                    'media' => $imageOrLink
                ];
            });
            // (mapping remains same...)
    
            return response()->json([
                'status' => 'success',
                'data' => $finalResponse
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
            $student_id = $request->student_id;
            $student = student::find($student_id);

            if (!$student) {
                return response()->json(['status' => 'error', 'message' => 'Student not found.'], 404);
            }

            $user_id = $student->user_id;

            // Create section list (1st from student model, rest from current enrollments)
            $sectionList = [$student->section_id];

            $currentSessionId = (new session())->getCurrentSessionId();
            if ($currentSessionId) {
                $enrollments = student_offered_courses::with(['offeredCourse:id,course_id'])
                    ->where('student_id', $student_id)
                    ->whereHas('offeredCourse', function ($query) use ($currentSessionId) {
                        $query->where('session_id', $currentSessionId);
                    })
                    ->get();

                $additionalSections = $enrollments->pluck('section_id')->unique()->toArray();
                $sectionList = array_unique(array_merge($sectionList, $additionalSections));
            }

            // Scenario 1: Broadcast to All (receiver is null)
            $broadcastAll = notification::where('Brodcast', 1)
                ->whereNull('reciever');

            // Scenario 2: Broadcast to Students
            $broadcastToStudents = notification::where('Brodcast', 1)
                ->where('reciever', 'Student');

            // Scenario 3: Direct to user
            $directToUser = notification::where('TL_receiver_id', $user_id);

            // Scenario 4: Section-based
            $sectionNotifications = notification::whereIn('Student_Section', $sectionList);

            // Merge all queries
            $notifications = $broadcastAll->get()
                ->merge($broadcastToStudents->get())
                ->merge($directToUser->get())
                ->merge($sectionNotifications->get());

            // Remove duplicates and sort by date
            $sortedNotifications = $notifications->unique('id')->sortByDesc('notification_date')->values();

            // Map and enhance each notification
            $finalResponse = $sortedNotifications->map(function ($note) {
                $senderName = 'System';
                $image = null;
                $type = null;
                $imageOrLink = null;

                if ($note->sender === 'Admin') {
                    $admin = admin::where('user_id', $note->TL_sender_id)->first();
                    if ($admin) {
                        $senderName = $admin->name ?? 'N/A';
                        $image = $admin->image ? asset($admin->image) : null;
                    }
                } else if ($note->sender === 'DataCell') {
                    $datacell = datacell::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender === 'JuniorLecturer') {
                    $datacell = juniorlecturer::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender === 'Teacher') {
                    $datacell = Teacher::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender == 'Director') {
                    $datacell = Director::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
                    }
                } else if ($note->sender == 'HOD') {
                    $datacell = Hod::where('user_id', $note->TL_sender_id)->first();
                    if ($datacell) {
                        $senderName = $datacell->name ?? 'N/A';
                        $image = $datacell->image ? asset($datacell->image) : null;
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
                    'media_type' => $type,
                    'media' => $imageOrLink
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $finalResponse
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function withdrawRequest($id)
    {
        $request = contested_attendance::find($id);

        if (!$request) {
            return response()->json([
                'message' => 'Contested attendance request not found.',
            ], 404);
        }
        $request->delete();

        return response()->json([
            'message' => 'Contested attendance request withdrawn successfully.',
        ], 200);
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

    public function submitQuizAnswer(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:student,id',
            'task_id' => 'required|exists:task,id',
            'Answer' => 'required|array'
        ]);

        $studentId = $request->student_id;
        $taskId = $request->task_id;
        $answers = $request->Answer;

        try {
            if (student_task_result::where('Student_id', $studentId)->where('Task_id', $taskId)->exists()) {
                return response()->json(['error' => 'You have already submitted this quiz.'], 409);
            }
            $task = Task::with(['teacherOfferedCourse', 'courseContent'])->findOrFail($taskId);
            $obtainedMarks = self::calculateQuizMarks($answers, $task->courseContent->id, $task->points);

            // Store result
            student_task_result::updateOrInsert(
                ['Task_id' => $taskId, 'Student_id' => $studentId],
                ['ObtainedMarks' => $obtainedMarks ?? 0]
            );

            // Section-wide task stats
            $teacherCourse = teacher_offered_courses::findOrFail($task->teacher_offered_course_id);
            $sectionId = $teacherCourse->section_id;
            $offeredCourseId = $teacherCourse->offered_course_id;

            $studentCount = student_offered_courses::where('section_id', $sectionId)
                ->where('offered_course_id', $offeredCourseId)
                ->count();

            $submissionCount = student_task_result::where('Task_id', $taskId)->count();

            if ($studentCount === $submissionCount) {
                Task::ChangeStatusOfTask($taskId);
            }

            return response()->json([
                'message' => 'Your MCQ quiz has been submitted!',
                'Obtained Marks' => $obtainedMarks,
                'Total Marks of Task' => $task->points,
                'Quiz Data' => Action::getMCQS($task->courseContent->id),
                'Your Submissions' => $answers
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'error' => 'An error occurred during quiz submission.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function submitFileAnswer(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:student,id',
            'task_id' => 'required|exists:task,id',
            'Answer' => 'required|file|max:10240'
        ]);
        $studentId = $request->student_id;
        $taskId = $request->task_id;
        try {
            if (student_task_submission::where('Student_id', $studentId)->where('Task_id', $taskId)->exists()) {
                return response()->json(['error' => 'Submission already exists.'], 409);
            }
            $student = Student::findOrFail($studentId);
            $task = Task::with(['teacherOfferedCourse', 'courseContent'])->findOrFail($taskId);
            if (strtoupper($task->courseContent->content) === 'MCQS') {
                return response()->json(['error' => 'MCQ submissions are not allowed through this endpoint.'], 403);
            }
            $sessionId = (new session())->getCurrentSessionId();
            $sessionData = session::findOrFail($sessionId);
            $teacherOffered = teacher_offered_courses::findOrFail($task->teacher_offered_course_id);
            $section = Section::findOrFail($teacherOffered->section_id);
            $offeredCourse = offered_courses::with('course')->findOrFail($teacherOffered->offered_course_id);
            $fileName = "({$student->RegNo})-{$task->title}";
            $directoryPath = "{$sessionData->name}-{$sessionData->year}/{$section->program}-{$section->semester}{$section->group}/{$offeredCourse->course->description}/Task";
            if ($request->hasFile('Answer') && $request->file('Answer')->isValid()) {
                $filePath = FileHandler::storeFile($fileName, $directoryPath, $request->file('Answer'));
                student_task_submission::create([
                    'Answer' => $filePath,
                    'DateTime' => now(),
                    'Student_id' => $studentId,
                    'Task_id' => $taskId,
                ]);
                return response()->json([
                    'message' => 'Your submission has been successfully added!',
                    'Total Marks of Task' => $task->points,
                    'Your Submission File' => asset($filePath)
                ], 200);
            }
            return response()->json(['error' => 'Invalid or missing file.'], 400);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'An error occurred during submission.',
                'details' => $e->getMessage()
            ], 500);
        }
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
    public static function getCourseContentWithTopicsForPrevious($offered_course_id, $teacher_offered_course_id = null)
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
                        $Status = 'Un-Covered';
                        if ($teacher_offered_course_id) {
                            $tStatus = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)->
                                where('topic_id', $topic->id)
                                ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                                ->first();
                            $Status = ($tStatus) ? ($tStatus->Status == 1 ? 'Covered' : 'Un-Covered') : 'Un-Covered';
                        }
                        $topics[] = [
                            'topic_name' => $topic->title,
                            'status' => $Status
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
                    'type' => $courseContent->content == 'MCQS' ? 'MCQS' : $courseContent->type,
                    'week' => $courseContent->week,
                    $courseContent->content == 'MCQS' ? 'MCQS' : 'File' => $courseContent->content == 'MCQS'
                        ? Action::getMCQS($courseContent->id)
                        : ($courseContent->content ? asset($courseContent->content) : null)
                ];
            }
        }
        ksort($result);
        return $result;
    }
    public static function getCourseContentWithTopicsForActive($offered_course_id, $teacher_offered_course_id = null)
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
                        $Status = 'Un-Covered';
                        if ($teacher_offered_course_id) {
                            $tStatus = t_coursecontent_topic_status::where('coursecontent_id', $courseContent->id)->
                                where('topic_id', $topic->id)
                                ->where('teacher_offered_courses_id', $teacher_offered_course_id)
                                ->first();
                            $Status = ($tStatus) ? ($tStatus->Status == 1 ? 'Covered' : 'Un-Covered') : 'Un-Covered';
                        }
                        $topics[] = [
                            'topic_name' => $topic->title,
                            'status' => $Status
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
                if ($teacher_offered_course_id) {
                    if (task::where('coursecontent_id', $courseContent->id)->where('teacher_offered_course_id', $teacher_offered_course_id)->where('due_date', '<', Carbon::now())->exists()) {
                        $result[$week][] = [
                            'course_content_id' => $courseContent->id,
                            'title' => $courseContent->title,
                            'type' => $courseContent->content == 'MCQS' ? 'MCQS' : $courseContent->type,
                            'week' => $courseContent->week,
                            $courseContent->content == 'MCQS' ? 'MCQS' : 'File' => $courseContent->content == 'MCQS'
                                ? Action::getMCQS($courseContent->id)
                                : ($courseContent->content ? asset($courseContent->content) : null)
                        ];
                    }
                }
            }
        }
        ksort($result);
        return $result;
    }
    public function GetFullCourseContentOfStudentByActivePrevious(Request $request)
    {
        try {
            $request->validate([
                'student_id' => 'required',
            ]);
            $student_id = $request->student_id;
            $enrollments = StudentManagement::getAllEnrollmentCoursesName($student_id);
            $ActiveCourseContent = [];
            $PreviousCourseContent = [];
            $CurrentSessionId = (new session())->getCurrentSessionId();
            foreach ($enrollments as $enroll) {
                $Is = 'Previous';
                if ($CurrentSessionId != 0) {
                    if ($enroll['session_id'] == $CurrentSessionId) {
                        $Is = 'Current';
                    }
                }
                $teacher_name = 'N/A';
                $teacher_name = teacher_offered_courses::with('teacher')->where('id', $enroll['teacher_offered_course_id'])->first()->teacher->name ?? 'N/A';
                $data = [
                    'course_name' => $enroll['course_name'],
                    'session' => $enroll['Session'],
                    'Section' => $enroll['Section'],
                    'teacher_name' => $teacher_name,

                ];





                if ($Is == 'Current') {
                    $data['course_content'] = self::getCourseContentWithTopicsForActive($enroll['offered_course_id'], $enroll['teacher_offered_course_id']);
                    $ActiveCourseContent[] = $data;
                } else {
                    $data['course_content'] = self::getCourseContentWithTopicsForPrevious($enroll['offered_course_id'], $enroll['teacher_offered_course_id']);
                    $PreviousCourseContent[] = $data;
                }
            }
            return response()->json([
                'success' => 'success',
                'data' => [
                    'Active' => $ActiveCourseContent,
                    'Previous' => $PreviousCourseContent
                ]
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
            $currentCourses = StudentManagement::getYourEnrollments($id);
            $previousCourses = StudentManagement::getYourPreviousEnrollments($id);
            return response()->json([
                'success' => 'Fetcehd Successfully !',
                'CurrentCourses' => $currentCourses,
                'PreviousCourses' => $previousCourses
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
    public static function getStudentAllExamResult($student_id)
    {

    }
    public function getStudentExamResult(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required',
        ]);
        $courses = StudentManagement::getAllEnrollmentCoursesName($request->student_id);
        if (!student::find($validated['student_id'])) {
            return 'No Student Found  !';
        }
        $studentId = $validated['student_id'];
        $Data = [];
        foreach ($courses as $course) {
            $offeredCourseId = $course['offered_course_id'];
            $exams = exam::where('offered_course_id', $offeredCourseId)->with(['questions'])->get();
            $results = [];
            $totalObtainedMarks = 0;
            $totalMarks = 0;
            $solidMarks = 0;
            foreach ($exams as $exam) {
                $examData = [
                    'exam_id' => $exam->id,
                    'exam_type' => $exam->type,
                    'exam_question_paper' => $exam->QuestionPaper ? asset($exam->QuestionPaper) : null,
                    'total_marks' => $exam->total_marks,
                    'solid_marks' => $exam->Solid_marks,
                    'obtained_marks' => 0,
                    'solid_marks_equivalent' => 0,
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
                $examData['solid_marks_equivalent'] = $exam->Solid_marks > 0
                    ? ($examTotalObtained / $exam->total_marks) * $exam->Solid_marks
                    : 0;
                $totalObtainedMarks += $examTotalObtained;
                $totalMarks += $exam->total_marks;
                $solidMarks += $exam->Solid_marks;

                $results[$exam->type][] = $examData;
            }
            $Data[] = [
                'course_name' => $course['course_name'],
                'session' => $course['Session'],
                'section' => $course['Section'],
                'total_marks' => $totalMarks,
                'solid_marks' => $solidMarks,
                'obtained_marks' => $totalObtainedMarks,
                'solid_marks_equivalent' => $solidMarks > 0
                    ? ($totalObtainedMarks / $totalMarks) * $solidMarks
                    : 0,
                'exam_results' => $results,
            ];


        }
        return response()->json([
            'status' => 'success',
            'data' => $Data
        ], 200);
    }

}
