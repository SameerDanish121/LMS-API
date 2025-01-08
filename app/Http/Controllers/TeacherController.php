<?php
namespace App\Http\Controllers;
use App\Models\Action;
use App\Models\attendance;
use App\Models\Attendance_Sheet_Sequence;
use App\Models\notification;
use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\teacher_offered_courses;
use App\Models\timetable;
use App\Models\venue;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
class TeacherController extends Controller
{
    // public function markAttendance(Request $request)
    // {
    //     $validatedData = $request->validate([
    //         'student_id' => 'required',
    //         'teacher_offered_course_id' =>'required',
    //         'status' => 'required|in:p,a',
    //     ]);
    //     try {
    //         $teacherCourse = teacher_offered_courses::find($validatedData['teacher_offered_course_id']);
    //         $student = student::find($validatedData['student_id']);
    //         if (!$teacherCourse || !$student) {
    //             return response()->json(['message' => 'Invalid teacher course or student'], 404);
    //         }
    //         attendance::create([
    //             'status' => $validatedData['status'],
    //             'date_time' => now(), 
    //             'isLab' =>false,
    //             'student_id' => $validatedData['student_id'],
    //             'teacher_offered_course_id' => $validatedData['teacher_offered_course_id'],
    //         ]);

    //         return response()->json(['message' => 'Attendance marked successfully'], 201);
    //     } catch (\Exception $e) {
    //         return response()->json(['message' => 'Error marking attendance', 'error' => $e->getMessage()], 500);
    //     }
    // }

    // public function FullTimetable(Request $request)
    // {
    //     try {
    //         $teacher_id=$request->teacher_id;
    //         $timetable = timetable::getFullTimetableByTeacherId($teacher_id);
    //         return response()->json([
    //             'status' => 'success',
    //             'data' => $timetable
    //         ], 200);
    //     } catch (ValidationException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);

    //     } catch (ModelNotFoundException $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Invalid username or password'
    //         ], 404);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'An unexpected error occurred',
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }


    public function getCurrentOfferedCourses($id)
    {
        // Get the current session ID using the method getCurrentSessionId
        $currentSessionId = session::getCurrentSessionId();

        // Check if the current session exists
        if ($currentSessionId == 0) {
            return response()->json(['error' => 'No active session found'], 404);
        }

        // Update the query to filter by the current session
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
        AND 
            oc.session_id = :current_session_id
    ";

        // Execute the query with the teacher ID and current session ID
        $courses = DB::select($query, [
            'teacher_id' => $id,
            'current_session_id' => $currentSessionId,
        ]);

        // Check if any results were returned
        if (empty($courses)) {
            return response()->json(['error' => 'Teacher not found or no courses available for the current session'], 404);
        }

        return response()->json($courses);
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

    public function getAllVenues()
    {
        // Get all venue names using the model
        $venues = venue::select('id', 'venue')->get();

        // Check if any venues are found
        if ($venues->isEmpty()) {
            return response()->json(['error' => 'No venues found'], 404);
        }

        return response()->json($venues);
    }
    public function getTodayClasses($teacherId)
    {
        // Get the current day name (e.g., 'Monday', 'Tuesday', etc.)
        $currentDay = Carbon::now()->format('l');

        // Get today's classes for the teacher
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
                'venue.venue AS venue_name',
                'course.name AS course_name',

                DB::raw("CONCAT(program.name, '-', section.semester, section.group) AS section"), // Concatenate program name
                'dayslot.day AS day_slot',
                'dayslot.start_time',
                'dayslot.end_time',
                'timetable.type AS class_type'
            )
            ->get();

        // Check if there are any classes for today
        if ($classes->isEmpty()) {
            return response()->json(['message' => 'No classes found for today'], 404);
        }

        // Format start and end times using Carbon
        $formattedClasses = $classes->map(function ($class) {
            $class->start_time = Carbon::parse($class->start_time)->format('g:i A');
            $class->end_time = Carbon::parse($class->end_time)->format('g:i A');
            return $class;
        });

        // Return the formatted data in JSON format
        return response()->json($formattedClasses);
    }

    public function getSortedAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'teacher_id' => 'required|integer',
            'section_id' => 'required|integer',
        ]);

        $teacherId = $validatedData['teacher_id'];
        $sectionId = $validatedData['section_id'];

        try {
            $attendanceData = Attendance_Sheet_Sequence::with(['teacherOfferedCourse', 'student'])
                ->whereHas('teacherOfferedCourse', function ($query) use ($teacherId, $sectionId) {
                    $query->where('teacher_id', $teacherId)
                        ->where('section_id', $sectionId);
                })
                ->orderBy('For', 'ASC')
                ->orderBy('SeatNumber', 'ASC')
                ->get();

            $formattedData = $attendanceData->map(function ($record) {
                return [
                    'teacher_offered_course_id' => $record->teacher_offered_course_id,
                    'student_id' => $record->student_id,
                    'For' => $record->For,
                    'SeatNumber' => $record->SeatNumber,
                    'RegNo' => $record->student->RegNo ?? null,
                    'name' => $record->student->name ?? null,
                    'cgpa' => $record->student->cgpa ?? null,
                    'image' => $record->student->image ?? null,
                    'section_id' => $record->teacherOfferedCourse->section_id ?? null,
                    'session_id' => $record->student->session_id ?? null,
                    'status' => $record->student->status ?? null,
                ];
            })->groupBy('For');

            $responseData = [];
            foreach ($formattedData as $key => $group) {
                $responseData[$key] = $group->values();
            }

            return response()->json([
                'success' => true,
                'data' => $responseData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch attendance data',
                'error' => $e->getMessage(),
            ], 500);
        }


    }

    public function getStudentsByTeacherAndSection(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|integer',
            'section_id' => 'required|integer',
        ]);

        $teacherId = $validated['teacher_id'];
        $sectionId = $validated['section_id'];

        try {
            // Fetching student data
            $students = student::select(
                'student.id',
                'student.RegNo',
                'student.name',
                'student.cgpa',
                'student.image as image', // Selecting the image column
                'section.id as section_id',
                DB::raw("CONCAT(section.program, '-', section.semester, section.group) AS Section")
            )
                ->join('student_offered_courses', 'student.id', '=', 'student_offered_courses.student_id')
                ->join('teacher_offered_courses', 'student_offered_courses.offered_course_id', '=', 'teacher_offered_courses.offered_course_id')
                ->join('section', 'student_offered_courses.section_id', '=', 'section.id')
                ->where('teacher_offered_courses.teacher_id', $teacherId)
                ->where('teacher_offered_courses.section_id', $sectionId)
                ->get();

            // Debugging and resolving the image paths
            foreach ($students as $s) {
                //Log::info('Original image path: ' . $s->image); // Log original path
                try {
                    $s->image = Action::getImageByPath($s->image); // Resolve the image path
                    //Log::info('Resolved image path: ' . $s->image); // Log resolved path
                } catch (\Exception $e) {
                    //Log::error('Error resolving image path: ' . $e->getMessage());
                    $s->image = null; // Fallback to null or a default value
                }
            }

            return response()->json([
                'success' => true,
                'data' => $students,
            ]);

        } catch (\Exception $e) {
            // Log the exception for debugging
            //Log::error('Error fetching students: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCourseDetails(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|integer',
        ]);

        $teacher_id = $validated['teacher_id'];

        $currentSessionId = session::getCurrentSessionId();

        if ($currentSessionId === 0) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $courseDetails = teacher_offered_courses::join('offered_courses', 'teacher_offered_courses.offered_course_id', '=', 'offered_courses.id')
            ->join('session', 'offered_courses.session_id', '=', 'session.id')
            ->join('course', 'offered_courses.course_id', '=', 'course.id')
            ->where('teacher_offered_courses.teacher_id', $teacher_id)
            ->where('offered_courses.session_id', $currentSessionId)
            ->select(
                'teacher_offered_courses.id AS teacher_offered_course_id',
                'teacher_offered_courses.section_id',
                'offered_courses.id AS offered_course_id',
                'offered_courses.course_id',
                'session.id AS session_id',
                'session.name AS session_name',
                'session.year AS session_year',
                'course.code AS course_code',
                'course.name AS course_name',
                'course.credit_hours',
                'course.pre_req_main',
                'course.type',
                'course.description',
                DB::raw("IF(course.lab = 1, 'Yes', 'No') as lab")
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $courseDetails
        ]);
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
            ]);

            $sender = $request->sender;

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
            ];

            if ($sender === 'Teacher' || $sender === 'JuniorLecturer') {
                $data['TL_sender_id'] = 113;
            } elseif ($sender === 'Admin' || $sender === 'Datacell') {
                $data['TL_sender_id'] = null;
            } else {
                return response()->json(['message' => 'Invalid sender role.'], 400);
            }

            $notification =notification::create($data);

            return response()->json(['message' => 'Notification sent successfully!', 'data' => $notification], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send notification.', 'error' => $e->getMessage()], 500);
        }
    }

    public function markAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'student_id' => 'required',
            'teacher_offered_course_id' => 'required',
            'status' => 'required|in:p,a',
        ]);
        try {
            $teacherCourse = teacher_offered_courses::find($validatedData['teacher_offered_course_id']);
            $student = student::find($validatedData['student_id']);
            if (!$teacherCourse || !$student) {
                return response()->json(['message' => 'Invalid teacher course or student'], 404);
            }
            attendance::create([
                'status' => $validatedData['status'],
                'date_time' => now(),
                'isLab' => false,
                'student_id' => $validatedData['student_id'],
                'teacher_offered_course_id' => $validatedData['teacher_offered_course_id'],
            ]);

            return response()->json(['message' => 'Attendance marked successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Error marking attendance', 'error' => $e->getMessage()], 500);
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
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
