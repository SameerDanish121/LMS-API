<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Models\admin;
use App\Models\Course;
use App\Models\coursecontent;
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
        } catch (\Exception $e) {
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation exceptions
            return response()->json([
                'error' => 'Validation Error',
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
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
        $GoodFormat=[];
        $sections = $query->get();
        foreach($sections as $section){
            $GoodFormat[]=[
                'id'=>$section->id,
                'Name'=>(new section())->getNameByID($section->id)
            ];
        }
        return response()->json($GoodFormat);
    }

    public function AllTeacher(Request $request)
    {
        try {
            // Initialize an empty array for teachers
            $teachers = [];

            // Check if specific filters are provided in the request and apply them
            if ($request->user_id) {
                // Search by user_id
                $teachers = teacher::where('user_id', $request->user_id)->first();
            } else if ($request->name) {
                // Search by teacher name
                $teachers = Teacher::where('name', $request->name)->get();
            } else {
                // If no filters are provided, get all teachers
                $teachers = Teacher::with(['user'])->get();
            }

            // Loop through each teacher to encode their image as base64
            foreach ($teachers as $teacher) {
                $originalPath = $teacher->image;
                if (file_exists(public_path($originalPath))) {
                    // If the image exists, convert it to base64
                    $imageContent = file_get_contents(public_path($originalPath));
                    $teacher->image = base64_encode($imageContent); // Set the base64 encoded image
                } else {
                    $teacher->image = null; // Set to null if the image doesn't exist
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
        } catch (\Exception $e) {
            // In case of an error, return the error details
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
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
        } catch (\Exception $e) {
            // Handle errors
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display a list of junior lectures with optional name search.
     */
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
        } catch (\Exception $e) {
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
}
