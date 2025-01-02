<?php
namespace App\Http\Controllers;
use App\Models\attendance;
use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\teacher_offered_courses;
use App\Models\timetable;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
class TeacherController extends Controller
{
    public function markAttendance(Request $request)
    {
        $validatedData = $request->validate([
            'student_id' => 'required',
            'teacher_offered_course_id' =>'required',
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
                'isLab' =>false,
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
            $teacher_id=teacher::where('name',$request->teacher_name)->pluck('id')->first();
            $timetable = timetable::with([
                'course:name,id,description',
                'teacher:name,id',
                'venue:venue,id',
                'dayslot:day,start_time,end_time,id',
                'juniorLecturer:name'
            ])
                ->where('teacher_id', $teacher_id)
                ->where('session_id', (new session())->getCurrentSessionId())
                ->get()
                ->map(function ($item) {
                    return [
                        'coursename' => $item->course->name,
                        'description' => $item->course->description,
                        'teachername' => $item->teacher->name ?? 'N/A',
                        'juniorlecturername' => $item->juniorLecturer->name ?? 'N/A',
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
