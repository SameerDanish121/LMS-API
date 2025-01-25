<?php

namespace App\Http\Controllers;
use App\Models\Action;
use App\Models\student_offered_courses;
use App\Models\teacher_juniorlecturer;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\ModuleException;
use App\Models\admin;
use App\Models\Course;
use App\Models\coursecontent;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use App\Models\datacell;
use App\Models\grader;
use App\Models\juniorlecturer;
use App\Models\notification;
use App\Models\section;
use App\Models\student;
use App\Models\teacher;
use App\Models\teacher_grader;
use Illuminate\Http\Request;
use App\Models\session;
use Carbon\Carbon;
use App\Models\user;
use Illuminate\Validation\ValidationException;
class AdminController extends Controller
{
    public function getStudentsNotEnrolledInSession($sessionId)
    {
        $students = DB::table('student as s')
            ->leftJoin('student_offered_courses as soc', 's.id', '=', 'soc.student_id')
            ->leftJoin('offered_courses as oc', 'soc.offered_course_id', '=', 'oc.id')
            ->whereNull('soc.id') // Ensures the student has no enrollment in any course
            ->orWhere(function ($query) use ($sessionId) {
                $query->whereNull('oc.id') // Ensures the student is not in any course for the given session
                    ->where('oc.session_id', $sessionId); // Specifically for the given session
            })
            ->select('s.id as student_id', 's.name as student_name', 's.RegNo as registration_number')
            ->get();

        // Check if no students found
        if ($students->isEmpty()) {
            return response()->json(['message' => 'No students found who are not enrolled in any course in this session'], 200);
        }

        return response()->json($students);
    }
    public function getStudentCoursesInSession($studentName, $sessionId)
    {
        $courses = DB::table('student as s')
            ->join('student_offered_courses as soc', 's.id', '=', 'soc.student_id')
            ->join('offered_courses as oc', 'soc.offered_course_id', '=', 'oc.id')
            ->join('course as c', 'oc.course_id', '=', 'c.id')
            ->join('section as sec', 'soc.section_id', '=', 'sec.id')
            ->where('s.name', 'LIKE', "%$studentName%")
            ->where('oc.session_id', $sessionId)
            ->select('s.name as student_name', 'c.name as course_name', DB::raw("CONCAT(sec.semester, sec.`group`) as section_details"))
            ->get();

        // Check if no courses found
        if ($courses->isEmpty()) {
            return response()->json(['message' => 'No courses found for this student in the given session'], 200);
        }

        return response()->json($courses);
    }
    public function getTeacherEnrolledCourses($teacherId, $sessionId)
    {
        $courses = DB::table('teacher as t')
            ->join('teacher_offered_courses as toc', 't.id', '=', 'toc.teacher_id')
            ->join('offered_courses as oc', 'toc.offered_course_id', '=', 'oc.id')
            ->join('course as c', 'oc.course_id', '=', 'c.id')
            ->join('section as s', 'toc.section_id', '=', 's.id')
            ->leftJoin('student_offered_courses as soc', function ($join) {
                $join->on('soc.section_id', '=', 's.id')
                    ->on('soc.offered_course_id', '=', 'oc.id');
            })
            ->where('t.id', $teacherId)
            ->where('oc.session_id', $sessionId)
            ->select(
                't.id as teacher_id',
                't.name as teacher_name',
                'c.name as course_name',
                DB::raw("CONCAT(s.program, ' ', s.semester, s.`group`) as section"), // Updated line
                DB::raw('COUNT(soc.id) as total_students')
            )
            ->groupBy('t.id', 't.name', 'c.name', 'section')
            ->get();

        if ($courses->isEmpty()) {
            return response()->json(['message' => 'No courses found for this teacher'], 200);
        }

        return response()->json($courses);
    }
    public function getTeachersWithNoCourses($sessionId)
    {
        // Fetch teachers who are not enrolled in the specified session
        $teachers = DB::table('teacher as t')
            ->leftJoin('teacher_offered_courses as toc', 't.id', '=', 'toc.teacher_id')
            ->leftJoin('offered_courses as oc', 'toc.offered_course_id', '=', 'oc.id')
            ->whereNull('oc.session_id')  // Check if the teacher is not associated with any course in the given session
            ->orWhere('oc.session_id', '!=', $sessionId)  // Ensure that the teacher is not enrolled in the provided session
            ->select('t.id as teacher_id', 't.name as teacher_name')
            ->groupBy('t.id', 't.name')
            ->get();

        // Check if the teachers array is empty and return a custom message
        if ($teachers->isEmpty()) {
            return response()->json(['message' => 'All teachers are enrolled in courses for this session'], 200);
        }

        // Return the teachers if found
        return response()->json($teachers);
    }
    public function getCoursesNotInSession($sessionId)
    {

        $courses = DB::table('course as c')
            ->whereNotIn('c.id', function ($query) use ($sessionId) {
                $query->select('oc.course_id')
                    ->from('offered_courses as oc')
                    ->where('oc.session_id', $sessionId);
            })
            ->select('c.id', 'c.code', 'c.name', 'c.credit_hours')
            ->get();

        // Check if the courses array is empty and return a custom message
        if ($courses->isEmpty()) {
            return response()->json(['message' => 'NO COURSES TO SHOW'], 200);
        }

        // Return the courses if found
        return response()->json($courses);
    }
    public function getCoursesInCurrentSession($sessionId)
    {
        // Get the current session ID using the getCurrentSessionId function


        // Fetch courses based on the current session ID
        $courses = DB::table('course as c')
            ->join('offered_courses as oc', 'c.id', '=', 'oc.course_id')
            ->where('oc.session_id', $sessionId)
            ->select('c.id', 'c.code', 'c.name', 'c.credit_hours')
            ->get();
        if ($courses->isEmpty()) {
            return response()->json(['message' => 'No course enrolled in this session'], 200);
        }
        return response()->json($courses);
    }
    public function AllStudent(Request $request)
    {
        try {
            $id = $request->id;
            $students = [];
            if ($request->student_RegNo) {
                $students = student::where('RegNo', $request->student_RegNo)->first();
            } else if ($request->student_name) {
                $students = student::where('name', $request->student_name)->get();
            } else if ($request->student_cgpa) {
                $students = student::where('cgpa', '>=', $request->student_cgpa)->get();
            } else if ($request->student_section) {
                $students = student::where('section_id', $request->student_section)->get();
            } else if ($request->student_program) {
                $students = student::where('program_id', $request->student_program)->get();
            } else if ($request->student_session) {
                $students = student::where('session_id', $request->student_session)->get();
            } else if ($request->student_status) {
                $students = student::where('status', $request->student_status)->get();
            } else {
                $students = student::with(['user', 'section', 'session', 'program'])->get();
            }
            foreach ($students as $student) {
                $originalPath = $student->image;
                if (!$originalPath) {
                    $student->image = null;
                } else if (file_exists(public_path($originalPath))) {
                    $imageContent = file_get_contents(public_path($originalPath));
                    $student->image = base64_encode($imageContent);
                } else {
                    $student->image = null;
                }
            }
            return response()->json(
                [
                    'message' => 'Student Fetched Successfully',
                    'Student' => $students,
                ],
                200
            );
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
            // Validate the request
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'required|string',
                'receiver' => 'required|string|in:student,teacher,datacell,jlecture', // Role type
                'notification_date' => 'required|date',
                'sender' => 'required|integer', // Admin sender ID
                'broadcast' => 'required|boolean', // Whether to broadcast
                'Student_Section' => 'nullable|integer', // For student notifications
                'TL_receiver_id' => 'nullable|integer', // For specific notifications
            ]);

            $receiverRole = $request->input('receiver');
            $broadcast = $request->input('broadcast');
            $senderId = $request->input('sender');
            $sectionId = $request->input('Student_Section');
            $tlReceiverId = $request->input('TL_receiver_id');

            // Initialize an empty array to hold target users
            $users = [];

            // Determine the target users based on the receiver role and conditions
            switch ($receiverRole) {
                case 'student':
                    if ($broadcast && !$sectionId) {
                        // Broadcast to all students
                        $users = user::where('role_id', 11)->get();
                    } elseif ($broadcast && $sectionId) {
                        // Broadcast to all students in a specific section
                        $users = User::where('role_id', 11)->where('section_id', $sectionId)->get();
                    }
                    break;

                case 'teacher':
                    if ($broadcast && !$tlReceiverId) {
                        // Broadcast to all teachers
                        $users = User::where('role_id', 2)->get();
                    } elseif ($broadcast && $tlReceiverId) {
                        // Send to a specific teacher based on TL_receiver_id
                        $users = User::where('role_id', 2)->where('id', $tlReceiverId)->get();
                    }
                    break;

                case 'datacell':
                    if ($broadcast && !$tlReceiverId) {
                        // Broadcast to all datacell users
                        $users = User::where('role_id', 3)->get();
                    } elseif ($broadcast && $tlReceiverId) {
                        // Send to a specific datacell user based on TL_receiver_id
                        $users = User::where('role_id', 3)->where('id', $tlReceiverId)->get();
                    }
                    break;

                case 'jlecture':
                    if ($broadcast && !$tlReceiverId) {
                        // Broadcast to all jlecture users
                        $users = User::where('role_id', 4)->get();
                    } elseif ($broadcast && $tlReceiverId) {
                        // Send to a specific jlecture user based on TL_receiver_id
                        $users = User::where('role_id', 4)->where('id', $tlReceiverId)->get();
                    }
                    break;

                default:
                    return response()->json(['error' => 'Invalid receiver role provided'], 400);
            }

            // Ensure we have target users
            if ($users->isEmpty()) {
                return response()->json(['error' => 'No users found for the given criteria'], 404);
            }

            // Create notifications for all determined users
            foreach ($users as $user) {
                notification::create([
                    'title' => $request->input('title'),
                    'description' => $request->input('description'),
                    'notification_date' => $request->input('notification_date'),
                    'sender' => $senderId,
                    'reciever' => $user->id,
                    'Brodcast' => $broadcast,
                    'TL_sender_id' => $senderId,
                    'Student_Section' => $sectionId,
                    'TL_receiver_id' => $tlReceiverId,
                ]);
            }

            return response()->json(['success' => 'Notification(s) sent successfully!']);
        } catch (ValidationException $e) {
            // Handle validation exceptions
            return response()->json([
                'error' => 'Validation Error',
                'message' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            // Handle general exceptions
            return response()->json([
                'error' => 'An error occurred while sending notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }
    public function showSections(Request $request)
    {
        // Initialize the query to fetch all sections
        $query = section::query();

        // Check if specific filters are provided in the request and apply them
        if ($request->has('group')) {
            $query->where('group', $request->input('group'));
        }

        if ($request->has('semester')) {
            $query->where('semester', $request->input('semester'));
        }
        if ($request->has('program')) {
            $query->where('program', $request->input('program'));
        }

        // Get the filtered or all sections based on the conditions
        $GoodFormat = [];
        $sections = $query->get();
        foreach ($sections as $section) {
            $GoodFormat[] = [
                'id' => $section->id,
                'Name' => (new section())->getNameByID($section->id)
            ];
        }
        return response()->json($GoodFormat);
    }

    // public function AllTeacher(Request $request)
    // {
    //     try {
    //         // Initialize an empty array for teachers
    //         $teachers = [];

    //         // Check if specific filters are provided in the request and apply them
    //         if ($request->user_id) {
    //             // Search by user_id
    //             $teachers = teacher::where('user_id', $request->user_id)->first();
    //         } else if ($request->name) {
    //             // Search by teacher name
    //             $teachers = Teacher::where('name', $request->name)->get();
    //         } else {
    //             // If no filters are provided, get all teachers
    //             $teachers = Teacher::with(['user'])->get();
    //         }

    //         // Loop through each teacher to encode their image as base64
    //         foreach ($teachers as $teacher) {
    //             $originalPath = $teacher->image;
    //             if (file_exists(public_path($originalPath))) {
    //                 // If the image exists, convert it to base64
    //                 $imageContent = file_get_contents(public_path($originalPath));
    //                 $teacher->image = base64_encode($imageContent); // Set the base64 encoded image
    //             } else {
    //                 $teacher->image = null; // Set to null if the image doesn't exist
    //             }
    //         }

    //         // Return the teachers as JSON with a success message
    //         return response()->json(
    //             [
    //                 'message' => 'Teacher Fetched Successfully',
    //                 'Teacher' => $teachers,
    //             ],
    //             200
    //         );
    //     } catch (\Exception $e) {
    //         // In case of an error, return the error details
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'An unexpected error occurred',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function AllCourse(Request $request)
    {
        try {
            // Initialize an empty array for courses
            $courses = [];

            // Check if specific filters are provided in the request and apply them
            if ($request->code) {
                // Search by course code
                $courses = Course::where('code', $request->code)->select('id', 'code', 'name', 'credit_hours', 'pre_req_main', 'lab')->get();
            } else if ($request->name) {
                // Search by course name
                $courses = Course::where('name', $request->name)->select('id', 'code', 'name', 'credit_hours', 'pre_req_main', 'lab')->get();
            } else {
                // If no filters are provided, get all courses with only the necessary fields
                $courses = Course::select('id', 'code', 'name', 'credit_hours', 'pre_req_main', 'lab')->get();
            }

            // Loop through each course to modify the 'pre_req_main' field and encode the response
            foreach ($courses as $course) {
                // Check if 'pre_req_main' is null and modify the value accordingly
                if (is_null($course->pre_req_main)) {
                    $course->pre_req_main = 'main'; // Set to "main" if null
                } else {
                    // If it's not null, get the prerequisite course name
                    $course->pre_req_main = $course->prerequisite ? $course->prerequisite->name : 'Not Available';
                }
            }

            // Return the courses as JSON with a success message
            return response()->json(
                [
                    'message' => 'Courses Fetched Successfully',
                    'Courses' => $courses,
                ],
                200
            );
        } catch (Exception $e) {
            // In case of an error, return the error details
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AllGrades(Request $request)
    {
        try {
            // Initialize an empty array for grades
            $grades = [];

            // Check if specific filters are provided in the request
            if ($request->student_id) {
                // If a student_id is provided, filter grades by student_id
                $grades = grader::where('student_id', $request->student_id)
                    ->with('student')  // Eager load related student data if needed
                    ->get();
            } else if ($request->status) {
                // If status is provided, filter grades by status
                $grades = Grader::where('status', $request->status)
                    ->with('student')  // Eager load related student data
                    ->get();
            } else {
                // If no filters are provided, return all grades
                $grades = Grader::with('student')  // Eager load related student data
                    ->get();
            }

            // Return the grades as JSON with a success message
            return response()->json(
                [
                    'message' => 'Grades Fetched Successfully',
                    'Grades' => $grades,
                ],
                200
            );
        } catch (Exception $e) {
            // In case of an error, return the error details
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getAllTeacherGraders(Request $request)
    {
        try {
            $teacherGraders = teacher_grader::with(['teacher'])
                ->get()
                ->map(function ($teacherGrader) {
                    return [
                        'id' => $teacherGrader->id,
                        'grader_id' => student::where('id', $teacherGrader->grader_id)->value('name') ?? 'N/A',
                        'teacher_name' => $teacherGrader->teacher?->name,
                        'session_id' => (new session())->getSessionNameByID(),
                        'status' => $teacherGrader->status,
                    ];
                });

            return response()->json([
                'message' => 'Teacher Grader data fetched successfully',
                'data' => $teacherGraders,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching teacher grader data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllSessions(Request $request)
    {
        try {
            // Fetch all sessions
            $sessions = session::all()->map(function ($session) {
                // Check if the session is active
                $currentDate = now();
                $isActive = $currentDate->between(Carbon::parse($session->start_date), Carbon::parse($session->end_date));

                return [
                    'id' => $session->id,
                    'name' => $session->name,
                    'year' => $session->year,
                    'start_date' => $session->start_date,
                    'end_date' => $session->end_date,
                    'status' => $isActive ? 'Active' : 'Inactive',
                ];
            });

            // Filter based on status if requested
            if ($request->has('status')) {
                $status = strtolower($request->status);
                if ($status === 'active') {
                    $sessions = $sessions->filter(fn($session) => $session['status'] === 'Active');
                } elseif ($status === 'inactive') {
                    $sessions = $sessions->filter(fn($session) => $session['status'] === 'Inactive');
                }
            }

            // Return the filtered sessions as JSON
            return response()->json([
                'message' => 'Sessions fetched successfully',
                'data' => $sessions->values(), // Reset array keys after filtering
            ], 200);
        } catch (Exception $e) {
            // Handle errors
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function allJuniorLecturers(Request $request)
    {
        try {
            // Initialize the query builder
            $query = juniorlecturer::query();

            // Apply a filter if the 'name' parameter is present
            if ($request->filled('name')) {
                $query->where('name', 'LIKE', '%' . $request->name . '%');
            }
            $lecturers = $query->get();
            foreach ($lecturers as $lecturer) {
                $originalPath = $lecturer->image;
                if ($originalPath && file_exists(public_path($originalPath))) {
                    // If the image exists, convert it to Base64
                    $imageContent = file_get_contents(public_path($originalPath));
                    $lecturer->image = base64_encode($imageContent); // Set the Base64-encoded image
                } else {
                    $lecturer->image = null; // Set to null if the image doesn't exist
                }
            }

            // Return the lecturers as JSON with a success message
            return response()->json(
                [
                    'message' => 'Junior Lecturers Fetched Successfully',
                    'lecturers' => $lecturers,
                ],
                200
            );
        } catch (Exception $e) {
            // Handle any errors and return an error response
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getCourseContent(Request $request)
    {
        // Fetch input data
        $courseName = $request->input('course_name'); // Optional
        $week = $request->input('week');             // Optional

        // Query course content with the related course name
        $query = coursecontent::with(['offeredCourse:course_id,name']); // Include the course name

        // Apply filter for course name if provided
        if ($courseName) {
            $query->whereHas('offeredCourse', function ($query) use ($courseName) {
                $query->where('courseName', 'LIKE', '%' . $courseName . '%'); // Partial match for flexibility
            });
        }

        // Apply filter for week if provided
        if ($week) {
            $query->where('week', $week);
        }

        // Fetch the results
        $courseContents = $query->get();

        // Return the results
        return response()->json([
            'success' => true,
            'data' => $courseContents->map(function ($content) {
                return [
                    'id' => $content->id,
                    'type' => $content->type,
                    'content' => $content->content,
                    'week' => $content->week,
                    'title' => $content->title,
                    'course_name' => $content->offeredCourse ? $content->offeredCourse->courseName : null,
                ];
            }),
        ]);
    }
    public function searchAdminByName(Request $request)
    {
        // Get the search query from the request
        $searchQuery = $request->input('name');

        // Perform the search or fetch all data if no search query
        $admins = admin::with('user')
            ->when($searchQuery, function ($query, $searchQuery) {
                return $query->where('name', 'LIKE', '%' . $searchQuery . '%');
            })
            ->get();

        // Transform the data to include only necessary fields
        $result = $admins->map(function ($admin) {
            return [
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'phone_number' => $admin->phone_number,
                'designation' => $admin->Designation,
                'image' => $admin->image,
                'username' => $admin->user->username ?? null,
                'password' => $admin->user->password ?? null,
                'email' => $admin->user->email ?? null,
            ];
        });

        return response()->json($result);
    }

    public function GetDatacell(Request $request)
    {
        // Get the search query from the request
        $searchQuery = $request->input('name');

        // Perform the search or fetch all data if no search query
        $datacells = datacell::with('user')
            ->when($searchQuery, function ($query, $searchQuery) {
                return $query->where('name', 'LIKE', '%' . $searchQuery . '%');
            })
            ->get();

        // Transform the data to include only necessary fields
        $result = $datacells->map(function ($datacells) {
            return [
                'admin_id' => $datacells->id,
                'admin_name' => $datacells->name,
                'phone_number' => $datacells->phone_number,
                'designation' => $datacells->Designation,
                'image' => $datacells->image,
                'username' => $datacells->user->username ?? null,
                'password' => $datacells->user->password ?? null,
                'email' => $datacells->user->email ?? null,
            ];
        });

        return response()->json($result);
    }




    ///////////////////////////////////////////
    public function GetTeacherByName(Request $request)
    {
        try {
            // Check if the 'name' parameter is provided in the request
            if (!$request->name) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Name parameter is required to fetch teacher.'
                ], 400);
            }

            // Search for the teacher by name
            $teacher = Teacher::where('name', 'like', '%' . $request->name . '%')->first();

            // If teacher is not found, return an error message
            if (!$teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher not found.'
                ], 404);
            }

            // If the teacher has an image, convert it to base64
            if ($teacher->image) {
                $imagePath = storage_path('app/public/images/' . $teacher->image);
                if (file_exists($imagePath)) {
                    $teacher->image = $imagePath;
                    // $imageContent = file_get_contents($imagePath);
                    // $teacher->image = base64_encode($imageContent); // Set the base64 encoded image
                } else {
                    $teacher->image = null; // Set to null if the image doesn't exist
                }
            }

            // Return the teacher's details as JSON
            return response()->json([
                'message' => 'Teacher fetched successfully.',
                'teacher' => $teacher
            ], 200);

        } catch (Exception $e) {
            // In case of an error, return the error details
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }







    public function AllTeacher(Request $request)
    {
        try {
            // Initialize an empty array for teachers
            $teachers = [];

            // Check if specific filters are provided in the request and apply them
            if ($request->user_id) {
                // Search by user_id
                $teachers = Teacher::where('user_id', $request->user_id)->first();
            } else if ($request->name) {
                // Search by teacher name
                $teachers = Teacher::where('name', 'like', '%' . $request->name . '%')->get();
            } else {
                // If no filters are provided, get all teachers
                $teachers = Teacher::with(['user'])->get();
            }

            // If a single teacher is returned (not a collection), wrap it in an array
            if ($teachers instanceof Teacher) {
                $teachers = [$teachers];
            }

            // Loop through each teacher to encode their image as base64
            foreach ($teachers as $teacher) {
                // Check if the teacher has an image
                if ($teacher->image) {
                    // Generate the full image path for the storage link (using public path correctly)
                    $imagePath = storage_path('app/public/images/' . $teacher->image);

                    // If the image exists, convert it to base64
                    if (file_exists($imagePath)) {
                        // $imageContent = file_get_contents($imagePath);
                        // $teacher->image = base64_encode($imageContent);
                        $teacher->image = $imagePath;
                    } else {
                        $teacher->image = null; // Set to null if the image doesn't exist
                    }

                    // Alternatively, return the URL to the image instead of base64 encoding
                    // $teacher->image = asset('storage/images/' . $teacher->image);
                }
            }

            // Return the teachers as JSON with a success message
            return response()->json(
                [
                    'message' => 'Teacher Fetched Successfully',
                    'Teacher' => $teachers,
                ],
                200
            );
        } catch (Exception $e) {
            // In case of an error, return the error details
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function AddOrUpdateSSingleTeacher(Request $request)
    {
        try {
            // Validate form data (name, date_of_birth, gender, email, and image)
            $request->validate([
                'name' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|string',
                'email' => 'required|email',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',  // Optional image
            ]);

            $name = $request->input('name');
            $dateOfBirth = $request->input('date_of_birth');
            $gender = $request->input('gender');
            $email = $request->input('email');

            // Generate a username and password for the user
            $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
            $formattedDOB = (new \DateTime($dateOfBirth))->format('Y-m-d');
            $password = Action::generateUniquePassword($name);

            // Add or update the user
            $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');
            if (!$userId) {
                return response()->json(['status' => 'failed', 'message' => 'Failed to create or update user for ' . $name], 400);
            }

            // Handle image upload if it exists
            $imagePath = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');

                // Generate the image file name using teacher's name
                $imageFileName = strtolower(str_replace(' ', '_', $name)) . '.' . $image->getClientOriginalExtension();

                // Store the image in the 'images' folder within the public storage
                $imagePath = $image->storeAs('images', $imageFileName, 'public');  // Store with teacher's name
            }

            // Check if the teacher already exists
            $teacher = Teacher::where('name', $name)->first();
            if ($teacher) {
                // Update the existing teacher with the new information
                $teacher->update([
                    'user_id' => $userId,
                    'date_of_birth' => $formattedDOB,
                    'gender' => $gender,
                    'image' => $imagePath ? basename($imagePath) : null,  // Save the image file name in DB
                ]);
                return response()->json(['status' => 'success', 'message' => 'Teacher updated successfully'], 200);
            } else {
                // Create a new teacher if not exists
                Teacher::create([
                    'user_id' => $userId,
                    'name' => $name,
                    'date_of_birth' => $formattedDOB,
                    'gender' => $gender,
                    'image' => $imagePath ? basename($imagePath) : null,  // Save the image file name in DB
                ]);
                return response()->json(['status' => 'success', 'message' => 'Teacher added successfully'], 200);
            }
        } catch (ValidationException $e) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'An error occurred', 'error' => $e->getMessage()], 500);
        }
    }

    public function getTeacherJuniorLecturers(Request $request)
    {
        try {
            // Validate the teacher_id
            $request->validate([
                'teacher_id' => 'required|exists:teacher,id', // Ensure 'teacher' matches the correct table
            ]);

            $teacher_id = $request->teacher_id;

            // Fetch junior lecturers for the specified teacher ID
            $juniorLecturers = teacher_juniorlecturer::with([
                'juniorLecturer',
                'teacherOfferedCourse.offeredCourse.session',
                'teacherOfferedCourse.offeredCourse.course',
                'teacherOfferedCourse.section',
            ])
                ->whereHas('teacherOfferedCourse', function ($query) use ($teacher_id) {
                    $query->where('teacher_id', $teacher_id);
                })
                ->get()
                ->map(function ($record) {
                    $section_id = $record->teacherOfferedCourse->section_id;
                    $sec = section::getNameByID($section_id);

                    $session = $record->teacherOfferedCourse->offeredCourse->session;
                    $formattedSession = $session ? strtoupper($session->name) . '-' . $session->year : 'N/A'; // Format as FALL-2024
    
                    $section_name = $record->teacherOfferedCourse->section ? $record->teacherOfferedCourse->section->name : 'N/A'; // Ensure section is loaded
    
                    return [
                        'junior_lecturer_name' => $record->juniorLecturer->name ?? 'N/A',
                        'teacher_offered_course_id' => $record->teacher_offered_course_id,
                        'course_name' => $record->teacherOfferedCourse->offeredCourse->course->name ?? 'N/A',
                        'session' => $formattedSession,
                        'section_name' => $sec,  // Use the condition to handle missing section data
                    ];
                });

            // Check if no junior lecturers found
            if ($juniorLecturers->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => "No junior lecturers Assigned to this teacher",
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $juniorLecturers,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors(),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function getAllTeachersWithJuniorLecturers(Request $request)
    {
        try {
            // Fetch all teachers with their junior lecturers and teacher-offered courses
            $teachersWithJuniorLecturers = teacher_juniorlecturer::with([
                'juniorLecturer',
                'teacherOfferedCourse.offeredCourse.session',
                'teacherOfferedCourse.offeredCourse.course',
                'teacherOfferedCourse.section',
                'teacherOfferedCourse.teacher'  // Fetch teacher information
            ])
                ->get();

            // Initialize an array to store the data
            $groupedTeachers = [];

            // Loop through the records and manually group them
            foreach ($teachersWithJuniorLecturers as $record) {
                $teacher = $record->teacherOfferedCourse->teacher; // Get the teacher for each course
                $section_id = $record->teacherOfferedCourse->section_id;
                $sec = Section::getNameByID($section_id);

                $session = $record->teacherOfferedCourse->offeredCourse->session;
                $formattedSession = $session ? strtoupper($session->name) . '-' . $session->year : 'N/A';

                // Define the item to add to the result
                $item = [
                    'junior_lecturer_name' => $record->juniorLecturer->name ?? 'N/A',
                    'teacher_offered_course_id' => $record->teacher_offered_course_id,
                    'course_name' => $record->teacherOfferedCourse->offeredCourse->course->name ?? 'N/A',

                    'section_name' => $sec,
                    'session' => $formattedSession,
                ];


                if (!isset($groupedTeachers[$teacher->name])) {
                    // If not, add the teacher with an empty junior_lecturers array
                    $groupedTeachers[$teacher->name] = [
                        'teacher_name' => $teacher->name ?? 'N/A',
                        'junior_lecturers' => [],
                    ];
                }

                // Add the junior lecturer to the teacher's list
                $groupedTeachers[$teacher->name]['junior_lecturers'][] = $item;
            }

            // Check if no junior lecturers found
            if (empty($groupedTeachers)) {
                return response()->json([
                    'status' => 'success',
                    'data' => "No junior lecturers assigned to any teachers.",
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $groupedTeachers,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function noClassesToday(Request $request)
    {
        try {
            // Validate teacher_id input
            $teacherId = $request->teacher_id;
            if (!$teacherId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Teacher ID is required.'
                ], 400);
            }

            // Fetch the free slots from the database
            $freeSlots = DB::table('dayslot as ds1')
                ->leftJoin('timetable as t1', function ($join) use ($teacherId) {
                    $join->on('t1.dayslot_id', '=', 'ds1.id')
                        ->where('t1.teacher_id', '=', $teacherId);
                })
                ->whereNull('t1.teacher_id') // Only show slots with no class scheduled
                ->select('ds1.day', 'ds1.start_time as free_start_time', 'ds1.end_time as free_end_time')
                ->orderBy('ds1.day')

                ->get();

            // If no free slots are found, return a message
            if ($freeSlots->isEmpty()) {
                return response()->json([
                    'status' => 'Following Are Free Slots ',
                    'message' => 'No free slots available for this teacher.',
                    'data' => []
                ], 200);
            }

            // Return the free slots
            return response()->json([
                'status' => 'success',
                'data' => $freeSlots
            ], 200);

        } catch (Exception $e) {
            // Handle any unexpected errors
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while fetching the free slots.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function getTeachersWithoutGraders(Request $request)
    {
        $currentSession = $request->json('session_id');
        if (!$currentSession) {
            return response()->json(['error' => 'Session ID is required'], 400);
        }

        try {
            // Fetch teachers without graders assigned in the current session
            $teachersWithoutGraders = Teacher::leftJoin('teacher_grader', function ($join) use ($currentSession) {
                $join->on('teacher.id', '=', 'teacher_grader.teacher_id')
                    ->where('teacher_grader.session_id', '=', $currentSession);
            })
                ->whereNull('teacher_grader.teacher_id')  // Not assigned in the current session
                ->orWhereIn('teacher.id', function ($query) use ($currentSession) {
                    $query->select('teacher_grader.teacher_id')
                        ->from('teacher_grader')
                        ->where('teacher_grader.session_id', '<', $currentSession) // Assigned in past sessions
                        ->whereNotIn('teacher_grader.teacher_id', function ($subQuery) use ($currentSession) {
                            $subQuery->select('teacher_grader.teacher_id')
                                ->from('teacher_grader')
                                ->where('teacher_grader.session_id', '=', $currentSession);  // Exclude those assigned in current session
                        });
                })
                ->select('teacher.id AS teacher_id', 'teacher.name AS teacher_name')
                ->get();

            return response()->json($teachersWithoutGraders);
        } catch (Exception $e) {
            Log::error('Error fetching teachers without graders: ' . $e->getMessage());
            return response()->json(['error' => 'Server error, please try again later'], 500);
        }
    }




    public function getTeachersWithAssignedGraders(Request $request)
    {
        try {
            // Fetch teachers and their assigned graders along with student names and session details
            $teachersWithGraders = DB::table('teacher')
                ->leftJoin('teacher_grader', 'teacher.id', '=', 'teacher_grader.teacher_id')
                ->leftJoin('grader', 'teacher_grader.grader_id', '=', 'grader.id')
                ->leftJoin('student', 'grader.student_id', '=', 'student.id') // Join student table
                ->leftJoin('session', 'teacher_grader.session_id', '=', 'session.id') // Join session table
                ->select(
                    'teacher.name AS teacher_name',
                    'student.name AS student_name',
                    'grader.id AS grader_id',
                    'grader.type AS grader_type',
                    'grader.status AS grader_status',
                    DB::raw("CONCAT('Session: ', session.name, '-', session.year) AS session_details")
                )
                ->whereNotNull('grader.id') // Only those with assigned graders
                ->orderBy('teacher.name') // Order by teacher's name
                ->get();

            // Group the result by teacher and map the nested graders
            $groupedResults = $teachersWithGraders->groupBy('teacher_name')->map(function ($items, $teacher_name) {
                return [
                    'teacher_name' => $teacher_name,
                    'graders' => $items->map(function ($item) {
                        return [
                            'student_name' => $item->student_name,

                            'grader_type' => $item->grader_type,
                            'grader_status' => $item->grader_status,
                            'session_details' => $item->session_details,
                        ];
                    }),
                ];
            });

            return response()->json($groupedResults);
        } catch (Exception $e) {
            Log::error('Error fetching teachers with graders: ' . $e->getMessage());
            return response()->json(['error' => 'Server error, please try again later'], 500);
        }
    }






    public function getUnassignedGraders(Request $request)
    {
        $currentSession = $request->json('session_id');
        if (!$currentSession) {
            return response()->json(['error' => 'Session ID is required'], 400);
        }

        try {
            // Get graders who are NOT assigned in the current session, including student names
            $unassignedGraders = grader::leftJoin('teacher_grader', function ($join) use ($currentSession) {
                $join->on('grader.id', '=', 'teacher_grader.grader_id')
                    ->where('teacher_grader.session_id', '=', $currentSession); // Join for current session
            })
                ->leftJoin('student', 'grader.student_id', '=', 'student.id') // Join student table
                ->whereNull('teacher_grader.grader_id')  // Graders not assigned in current session
                ->orWhere(function ($query) use ($currentSession) {
                    $query->whereIn('grader.id', function ($subquery) use ($currentSession) {
                        $subquery->select('grader.id')
                            ->from('teacher_grader')
                            ->where('teacher_grader.session_id', '<', $currentSession); // Assigned in previous sessions
                    })
                        ->whereNotIn('grader.id', function ($subquery) use ($currentSession) {
                            $subquery->select('grader.id')
                                ->from('teacher_grader')
                                ->where('teacher_grader.session_id', '=', $currentSession); // Not assigned in the current session
                        });
                })
                ->select('student.name AS Grader_name', 'grader.type AS Grader_type', 'grader.status AS Grader_status')
                ->get();

            return response()->json($unassignedGraders);
        } catch (Exception $e) {
            Log::error('Error fetching unassigned graders: ' . $e->getMessage());
            return response()->json(['error' => 'Server error, please try again later'], 500);
        }
    }


    public function getFailedStudents(Request $request)
    {
        try {
            // Validate offered_course_id
            $request->validate([
                'offered_course_id' => 'required|exists:offered_courses,id',
            ]);

            $offered_course_id = $request->offered_course_id;

            // Fetch failed students along with session details from the offered_courses relationship
            $failedStudents = student_offered_courses::with(['student', 'offeredCourse.session'])
                ->where('offered_course_id', $offered_course_id)
                ->where('grade', 'F') // Assuming 'F' represents a failing grade
                ->get()
                ->map(function ($record) {
                    $session = $record->offeredCourse->session;
                    $formattedSession = $session ? strtoupper($session->name) . '-' . $session->year : 'N/A'; // Format as FALL-2024
    
                    return [

                        'student_name' => $record->student->name ?? 'N/A',
                        'grade' => $record->grade,
                        'attempt_no' => $record->attempt_no,

                        'session' => $formattedSession, // FALL-2024 format
                    ];
                });

            // Check if there are no failed students
            if ($failedStudents->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'data' => "No students failed this course",
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $failedStudents,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->errors(),
            ], 400);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }











    public function getGraderHistory(Request $request)
    {
        try {
            $graderId = $request->json('grader_id');

            if (!$graderId) {
                return response()->json(['error' => 'Grader ID is required'], 400);
            }

            // Run the raw SQL query with formatted session
            $query = "
                SELECT 
                    s.name AS student_name,
                    CONCAT(ss.name, '-', ss.year) AS session_name, -- Format session as 'Fall-2024'
                   
                   
                    g.type AS grader_type,
                    g.status AS grader_status,
                
                    t.name AS teacher_name,
                  
                    tg.feedback
                FROM 
                    grader g
                LEFT JOIN 
                    teacher_grader tg ON g.id = tg.grader_id
                LEFT JOIN 
                    teacher t ON tg.teacher_id = t.id
                JOIN 
                    student s ON g.student_id = s.id
                JOIN 
                    session ss ON tg.session_id = ss.id
                WHERE 
                    g.id = :graderId
                ORDER BY 
                    tg.session_id DESC
            ";

            // Execute the query
            $result = DB::select($query, ['graderId' => $graderId]);

            // Check if the result is empty
            if (empty($result)) {
                return response()->json(['message' => 'Grader not found.'], 404);
            }

            // Format the response
            $response = $result;

            return response()->json($response, 200);
        } catch (Exception $e) {
            Log::error('Error fetching grader history: ' . $e->getMessage());
            return response()->json(['error' => 'Server error, please try again later.'], 500);
        }
    }
    public function addSingleSession(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string',
                'year' => 'required|integer',
                'start_date' => 'required|date_format:Y-m-d',
                'end_date' => 'required|date_format:Y-m-d',
            ]);

            $name = trim($request->input('name'));
            $year = trim($request->input('year'));
            $startDate = trim($request->input('start_date'));
            $endDate = trim($request->input('end_date'));

            $RawData = "Name = {$name}, Year = {$year}, Start Date = {$startDate}, End Date = {$endDate}";

            // Validate date order
            $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
            $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);

            if ($startDateObj->greaterThanOrEqualTo($endDateObj)) {
                return response()->json([
                    "status" => "error",
                    "message" => "End date must be greater than start date. {$RawData}"
                ], 422);
            }

            // Check for date overlap
            $overlap = session::where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($query) use ($startDate, $endDate) {
                        $query->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })->exists();

            if ($overlap) {
                return response()->json([
                    "status" => "error",
                    "message" => "Date range overlaps with an existing session. {$RawData}"
                ], 422);
            }
            $existingSession = session::where('name', $name)
                ->where('year', $year)
                ->first();

            if ($existingSession) {
                $existingSession->update([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
                return response()->json([
                    "status" => "success",
                    "message" => "Updated existing session. {$RawData}"
                ], 200);
            } else {
                session::create([
                    'name' => $name,
                    'year' => $year,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ]);
                return response()->json([
                    "status" => "success",
                    "message" => "Inserted new session. {$RawData}"
                ], 201);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function assignSingleGrader(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'reg_no' => 'required|string',
                'teacher_name' => 'required|string',
                'type' => 'required|in:Merit,Need',
                'session_name' => 'required|string'
            ]);

            // Extract parameters from the request
            $regNo = $request->input('reg_no');
            $teacherName = $request->input('teacher_name');
            $type = $request->input('type');
            $sessionName = $request->input('session_name');

            // Get the session ID by session name
            $sessionId = (new Session())->getSessionIdByName($sessionName);
            if (!$sessionId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Invalid session name: {$sessionName}"
                ], 400);
            }

            // Find the student by RegNo
            $studentId = Student::where('RegNo', $regNo)->value('id');
            if (!$studentId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Student with RegNo {$regNo} not found."
                ], 404);
            }

            // Check if the grader already exists for the student
            $grader = Grader::where('student_id', $studentId)->first();
            if ($grader) {
                // Update existing grader
                $grader->update([
                    'type' => $type,
                    'status' => 'active'
                ]);
            } else {
                // Create new grader if not found
                $grader = Grader::create([
                    'student_id' => $studentId,
                    'type' => $type,
                    'status' => 'active'
                ]);
            }

            // Find the teacher by full name
            $teacherId = Teacher::where('name', $teacherName)->value('id');
            if (!$teacherId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Teacher with name {$teacherName} not found."
                ], 404);
            }
            $checkGrader = teacher_grader::where('grader_id', $grader->id)
                ->where('session_id', $sessionId)
                ->get();
            if (count($checkGrader) > 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => "The Grader is already assigned to a different teacher in this session. Cannot assign one grader to multiple teachers: RegNo {$regNo}.",
                    'teacher' => $teacherName,
                    'session' => $sessionName
                ], 400);
            }
            $teacherGrader = teacher_grader::where([
                'grader_id' => $grader->id,
                'teacher_id' => $teacherId,
                'session_id' => $sessionId
            ])->first();

            if (!$teacherGrader) {
                teacher_grader::create([
                    'grader_id' => $grader->id,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId,
                    'feedback' => ''  // Optional field for feedback
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => "Grader assigned successfully: RegNo {$regNo}.",
                    'teacher' => $teacherName,
                    'session' => $sessionName
                ], 200);
            } else {
                return response()->json([
                    'status' => 'success',
                    'message' => "Grader is already assigned to the teacher: RegNo {$regNo}.",
                    'teacher' => $teacherName,
                    'session' => $sessionName
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
    public function AddOrUpdateSingleCourse(Request $request)
    {
        try {
            // Validate the incoming request data
            $request->validate([
                'code' => 'required|string',
                'name' => 'required|string',
                'credit_hours' => 'required|integer',
                'pre_req_main' => 'nullable|string', // Can be null
                'program_id' => 'nullable|integer', // Can be null
                'type' => 'required|in:Core,Elective', // Only Core or Elective, case-sensitive
                'description' => 'nullable|string', // Can be null
                'lab' => 'required|in:0,1' // lab can only be 0 or 1
            ]);

            // Get the input values
            $code = $request->input('code');
            $name = $request->input('name');
            $creditHours = $request->input('credit_hours');
            $preReqMain = $request->input('pre_req_main') ? $request->input('pre_req_main') : null;
            $programId = $request->input('program_id') ? $request->input('program_id') : null;
            $type = $request->input('type');
            $description = $request->input('description') ? $request->input('description') : null;
            $lab = $request->input('lab');

            // Handle the pre-requisite course if it exists
            if ($preReqMain) {
                $preReqId = Course::where('name', $preReqMain)->value('id');
                if (!$preReqId) {
                    return response()->json([
                        'status' => 'failed',
                        'reason' => "The prerequisite course {$preReqMain} does not exist!"
                    ], 400);
                }
            } else {
                $preReqId = null;
            }

            // Check if the course already exists
            $course = Course::where('name', $name)->where('code', $code)->first();

            // Data to update or create
            $dataToSave = [
                'code' => $code,
                'name' => $name,
                'credit_hours' => $creditHours,
                'type' => $type,
                'description' => $description,
                'lab' => $lab,
                'pre_req_main' => $preReqId,
                'program_id' => $programId
            ];

            if ($course) {
                // Update the course if it exists
                $course->update($dataToSave);
                return response()->json([
                    'status' => 'success',
                    'message' => "The course with Name: {$name} and Code: {$code} was updated successfully."
                ], 200);
            } else {
                // Create a new course if it doesn't exist
                Course::create($dataToSave);
                return response()->json([
                    'status' => 'success',
                    'message' => "The course with Name: {$name} and Code: {$code} was added successfully."
                ], 201);
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
}
