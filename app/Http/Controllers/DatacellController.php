<?php

namespace App\Http\Controllers;

use App\Models\teacher;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Http\Request;
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
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;
class DatacellController extends Controller
{
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
                if (file_exists(public_path($originalPath))) {
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
    public function AddNewOfferedCourse(Request $request)
    {
        try {
            $course_name = $request->course_name;
            if ($course_name) {
                $course_id = Course::where('name', $course_name)->pluck('id')->first();
                if ($course_id) {
                    $offered_course = offered_courses::where('course_id', $course_id)->where('session_id', (new session())->getCurrentSessionId())->first();
                    if ($offered_course) {
                        throw new Exception('Course is Already is Offerd in this session');
                    } else {
                        offered_courses::create(
                            [
                                'course_id' => $course_id,
                                'session_id' => (new session())->getCurrentSessionId()
                            ]
                        );
                        return response()->json(
                            [
                                'message' => 'Offerd Course Successfully Added'
                            ],
                            200
                        );

                    }

                } else {
                    throw new Exception('Course Name is Not in the Course List');
                }
            } else {
                throw new Exception('Course Name Required');
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function NewEnrollment(Request $request)
    {
        try {
            $validated = $request->validate([
                'student_RegNo' => 'required|string',
                'course_name' => 'required|string',
                'section' => 'required'
            ]);
            $student_RegNo = $request->student;
            $student = student::where('RegNo', $student_RegNo)->pluck('id')->first();
            $course_name = $request->course_name;
            $course = course::where('name', $course_name)->pluck('id')->first();
            $session = $request->session;
            $section = $request->section;
            if (!$session) {
                $session = (new session())->getCurrentSessionId();
            } else {
                $session_info = explode('-', $session);
                $session = session::where('year', $session_info[1])->where('name', $session_info[0])->pluck('id')->first();
            }
            $offered_course = offered_courses::where('session_id', $session)->where('course_id', $course)->pluck('id')->first();
            if (!$offered_course) {
                throw new Exception('Course is Not Offered in Provided Session ');
            }
            $section_info = $splitString = explode('-', $section);
            $program = $section_info[0];
            $semester = $section_info[1][0];
            $group = $section_info[1][1];
            $section = section::where('program', $program)->where('group', $group)->where('semester', $semester)->pluck('id')->first();
            $enrollment = student_offered_courses::where('student_id', $student)->where('section_id', $section)->where('offered_course_id', $offered_course);
            if ($enrollment) {
                throw new Exception('Enrollment Already Exsists');
            } else {
                $enrollment = student_offered_courses::where('student_id', $student)
                    ->whereHas('offeredCourse', function ($query) use ($course) {
                        $query->where('course_id', $course);
                    })
                    ->where('grade', 'F')
                    ->first();
                if ($enrollment) {
                    student_offered_courses::where('id', $enrollment->id)->update(
                        [
                            'grade' => '',
                            'offered_course_id' => $offered_course,
                            'section_id' => $section,
                            'attempt_no' => DB::raw('attempt_no + 1')
                        ]
                    );
                    return response()->json(
                        [
                            'message' => 'The Re-Enrollment For Failed Course is Added'
                        ],
                        200
                    );
                } else {
                    student_offered_courses::create(
                        [
                            'grade' => null,
                            'attempt_no' => 0,
                            'student_id' => $student,
                            'section_id' => $section,
                            'offered_course_id' => $offered_course
                        ]
                    );

                    return response()->json(
                        [
                            'message' => 'The Enrollment is Added'
                        ],
                        200
                    );
                }

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
    public function UploadTimetable(Request $request)
    {
        try {
            $merge = $request->merge;
            if ($merge) {

            } else {

            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    ////////////////////////////////////////////////////////////////////////////////////
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


    public function uploadExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
                'session' => 'required'
            ]);
            $session_name = $request->session;
            $session_id = (new session())->getSessionIdByName($session_name);
            if (!$session_id) {
                throw new Exception('NO SESSION EXISIT FOR THE PROVIDED NAME ');
            }
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $filteredData = [];
            foreach ($sheetData as $row) {
                $filteredData[] = array_slice($row, 0, 4);
            }
            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    if ($row['A'] != 'Section') {
                        $row = array_map('trim', $row);
                        $nonEmpty[] = $row;
                    }

                }
            }
            $FaultyData = [];
            $Succesfull = [];
            foreach ($nonEmpty as $index => $row) {
                $section_id = (new section())->getIDByName($row['A']);
                if (!$section_id) {
                   $section_id=section::addNewSection($row['A']);
                }
                $course_id = (new course())->getIDByName($row['B']);
                if (!$course_id) {
                    $FaultyData[] = $row;
                    continue;
                }
                $teacher_id = (new teacher())->getIDByName($row['D']);
                if (!$teacher_id) {
                    $FaultyData[] = $row;
                    continue;
                }
                $offered_course = offered_courses::firstOrCreate(
                    [
                        "course_id" => $course_id,
                        "session_id" => $session_id
                    ]
                );
                $offered_course_id = $offered_course->id;
                teacher_offered_courses::firstOrCreate(
                    [
                        "teacher_id" => $teacher_id,
                        "offered_course_id" => $offered_course_id,
                        "section_id" => $section_id,
                    ]
                );
                $Succesfull[] = $row;
            }
            return response()->json(
                [
                    'message' => 'The Teacher Enrollments Are Added',
                    'data' => [
                        "Sucessfull" => $Succesfull,
                        "FaultyData" => $FaultyData
                    ]
                ],
                200
            );
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
}
