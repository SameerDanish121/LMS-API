<?php

namespace App\Http\Controllers;

use DateTime;
use Exception;
use App\Models;
use Carbon\Carbon;
use App\Models\exam;
use App\Models\role;
use App\Models\task;
use App\Models\User;
use App\Models\topic;
use App\Models\venue;
use App\Models\Action;
use App\Models\course;
use App\Models\grader;
use App\Models\dayslot;
use App\Models\program;
use App\Models\section;
use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\question;
use App\Models\timetable;
use Laravel\Pail\Options;
use App\Models\attendance;
use App\Models\FileHandler;
use App\Models\notification;
use Illuminate\Http\Request;
use App\Models\coursecontent;
use App\Models\excluded_days;
use App\Models\sessionresult;
use App\Models\subjectresult;
use App\Models\juniorlecturer;
use App\Models\quiz_questions;
use App\Models\teacher_grader;
use App\Models\offered_courses;
use App\Models\StudentManagement;
use App\Models\task_consideration;
use Illuminate\Support\Facades\DB;
use App\Models\coursecontent_topic;
use App\Models\student_exam_result;
use App\Models\student_task_result;
use App\Traits\SendNotificationTrait;
use App\Models\teacher_juniorlecturer;
use GrahamCampbell\ResultType\Success;
use App\Models\student_offered_courses;
use App\Models\student_task_submission;
use App\Models\teacher_offered_courses;
use function PHPUnit\Framework\isEmpty;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ExtraKhattaController extends Controller
{ // Include the trait
    public static function EligibleToEnroll($student_id, $section_id)
    {
        $section = Section::find($section_id);
        if (!$section || !isset($section->semester)) {
            return false;
        }
        $student = student::find($student_id);
        if (!$student) {
            return false; 
        }
        $currentSemester = $section->semester;
        $promotionPolicies = [
            2 => 2.0,
            3 => 2.1,
            4 => 2.2,
            5 => 2.3,
            6 => 2.4,
            7 => 2.5,
            8 => 2.6
        ];
    
        // Get required CGPA (default to 2.5 if semester is not listed)
        $requiredCgpa = $promotionPolicies[$currentSemester] ?? 2.5;
    
        // Check CGPA condition
        if ($student->cgpa < $requiredCgpa) {
            return false;
        }
        return true; // Student meets promotion criteria
    }
    

    public function AddSubjectResult(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|file|mimes:xlsx,xls',
                'section_id' => 'required',
                'offered_course_id' => 'required|integer',
            ]);
            $section_id = $request->section_id;
            $section_id = (new section())->getIDByName($section_id);
            $offered_course_id = $request->offered_course_id;
            $offeredCourse = offered_courses::with('course:id,name,credit_hours,lab')->findOrFail($offered_course_id);
            $course = $offeredCourse->course;
            $isLab = $course->lab == 1;
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();
            $header = $rows[0];
            $expectedHeaders = ['RegNo', 'Name', 'Mid', 'Internal', 'Final', 'QualityPoints', 'Grade'];
            if ($isLab) {
                $expectedHeaders[] = 'Lab';
            }

            if (array_slice($header, 0, count(value: $expectedHeaders)) !== $expectedHeaders) {
                throw new Exception("Invalid header format. Expected: " . implode(", ", $expectedHeaders));
            }
            $filteredData = array_slice($rows, 1);
            $dbStudents = student_offered_courses::with('student')
                ->where('offered_course_id', $offered_course_id)
                ->where('section_id', $section_id)
                ->get();
            $dbStudentIds = $dbStudents->pluck('student.id')->toArray();
            $excelStudentIds = [];
            $success = [];
            $faultyData = [];
            foreach ($filteredData as $row) {
                $regNo = $row[0];
                $name = $row[1];
                $mid = $row[2] ?? 0;
                $internal = $row[3] ?? 0;
                $final = $row[4] ?? 0;
                $qualityPoints = $row[5] ?? null;
                $grade = $row[6] ?? null;
                $lab = $isLab ? ($row[7] ?? 0) : null;
                $student = student::where('RegNo', $regNo)->first();
                if (!$student) {
                    $faultyData[] = ["status" => "error", "issue" => "Student with RegNo {$regNo} not found"];
                    continue;
                }
                $excelStudentIds[] = $student->id;

                $studentOfferedCourse = student_offered_courses::where('student_id', $student->id)
                    ->where('offered_course_id', $offered_course_id)
                    ->first();
                if (!$studentOfferedCourse) {
                    $faultyData[] = ["status" => "error", "issue" => "Student not enrolled in the course"];
                    continue;
                }
                if ($isLab && $lab !== null && $lab < 8) {
                    $grade = 'F';
                }
                $existingResult = subjectresult::where('student_offered_course_id', $studentOfferedCourse->id)->first();
                if ($existingResult) {
                    $existingResult->update([
                        'mid' => $mid,
                        'internal' => $internal,
                        'final' => $final,
                        'lab' => $lab,
                        'grade' => $grade,
                        'quality_points' => $qualityPoints,
                    ]);
                    $GPA = self::calculateAndStoreGPA($student->id, $offeredCourse->session_id);
                    $CGPA = self::calculateAndUpdateCGPA($student->id);
                    $success[] = ["status" => "success", "logs" => "Result Already Exsist ! Updated the cradentials For {$regNo} | Updated GPA {$GPA} | Updated CGPA {$CGPA}"];
                    continue;
                }
                subjectresult::create([
                    'student_offered_course_id' => $studentOfferedCourse->id,
                    'mid' => $mid,
                    'internal' => $internal,
                    'final' => $final,
                    'lab' => $lab,
                    'grade' => $grade,
                    'quality_points' => $qualityPoints,
                ]);
                $studentOfferedCourse->update(['grade' => $grade]);
                $GPA = self::calculateAndStoreGPA($student->id, $offeredCourse->session_id);
                $CGPA = self::calculateAndUpdateCGPA($student->id);
                $success[] = ["status" => "success", "message" => "Result added for {$regNo} | Updated GPA IS {$GPA} |  Updated CGPA {$CGPA}"];
            }
            $missingStudentIds = array_diff($dbStudentIds, $excelStudentIds);
            foreach ($missingStudentIds as $missingId) {
                $studentOfferedCourse = student_offered_courses::where('student_id', $missingId)
                    ->where('offered_course_id', $offered_course_id)
                    ->first();
                if ($studentOfferedCourse) {
                    subjectresult::updateOrCreate(
                        [
                            'student_offered_course_id' => $studentOfferedCourse->id,
                        ],
                        [
                            'mid' => 0,
                            'internal' => 0,
                            'final' => 0,
                            'lab' => $isLab ? 0 : null,
                            'grade' => 'F',
                            'quality_points' => 0,
                        ]
                    );
                    $studentOfferedCourse->update(['grade' => 'F']);
                    $GPA = self::calculateAndStoreGPA($missingId, $offeredCourse->session_id);
                    $CGPA = self::calculateAndUpdateCGPA($missingId);
                    $faultyData[] = ["status" => "error", "issue" => "Missing record for student {$missingId}. Assigned grade 'F' Updated GPA {$GPA} | Updated CGPA {$CGPA}"];
                }
            }
            return response()->json([
                'status' => 'success',
                'Total Records' => count($filteredData),
                'Added' => count($success),
                'Failed' => count($faultyData),
                'Faulty Data' => $faultyData,
                'Success' => $success,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'errors' => $e->getMessage(),
            ], 500);
        }
    }
    public static function getTotalQualityPoints($student_id, $session_id)
    {
        // Validate inputs
        if (!$student_id || !$session_id) {
            return 0; // Return 0 if either parameter is not provided
        }

        // Get the enrollments for the given student_id and session_id
        $studentCourses = student_offered_courses::where('student_id', $student_id)
            ->whereHas('offeredCourse', function ($query) use ($session_id) {
                $query->where('session_id', $session_id);
            })
            ->get();
        if ($studentCourses->isEmpty()) {
            return 0;
        }
        $totalQualityPoints = 0;
        foreach ($studentCourses as $studentCourse) {
            $subjectResult = subjectresult::where('student_offered_course_id', $studentCourse->id)->first();
            if ($subjectResult) {
                $totalQualityPoints += $subjectResult->quality_points;
            }
        }
        return $totalQualityPoints;
    }
    public static function calculateCreditHours($student_id, $session_id)
    {
        if (!$student_id || !$session_id) {
            return 0;
        }
        $enrollments = student_offered_courses::with(['offeredCourse.course'])->where('student_id', $student_id)
            ->whereHas('offeredCourse', function ($query) use ($session_id) {
                $query->where('session_id', $session_id);
            })
            ->get();
        if ($enrollments->isEmpty()) {
            return 0;
        }
        $totalCreditHours = $enrollments->reduce(function ($carry, $enrollment) {
            return $carry + $enrollment->offeredCourse->course->credit_hours;
        }, 0);
        return $totalCreditHours;
    }
    public static function calculateAndStoreGPA($student_id, $session_id)
    {
        $totalQualityPoints = self::getTotalQualityPoints($student_id, $session_id);
        $totalCreditHours = self::calculateCreditHours($student_id, $session_id);
        if ($totalCreditHours == 0) {
            return 0;
        }
        $GPA = $totalQualityPoints / $totalCreditHours;
        $sessionResult = sessionresult::where('student_id', $student_id)
            ->where('session_id', $session_id)
            ->first();
        if ($sessionResult) {
            $sessionResult->update([
                'GPA' => $GPA,
                'Total_Credit_Hours' => $totalCreditHours,
                'ObtainedCreditPoints' => $totalQualityPoints,
            ]);
        } else {
            sessionresult::create([
                'student_id' => $student_id,
                'session_id' => $session_id,
                'GPA' => $GPA,
                'Total_Credit_Hours' => $totalCreditHours,
                'ObtainedCreditPoints' => $totalQualityPoints,
            ]);
        }

        return $GPA;
    }
    public static function calculateAndUpdateCGPA($student_id)
    {
        if (!$student_id) {
            return false;
        }
        $cgpa = self::calculateCGPA($student_id);
        $student = student::find($student_id);
        if (!$student) {
            return false;
        }
        $student->cgpa = $cgpa;
        $student->save();

        return $cgpa;
    }
    public static function calculateCGPA($student_id)
    {
        if (!$student_id) {
            return 0;
        }
        $sessionResults = sessionresult::where('student_id', $student_id)->get();

        if ($sessionResults->isEmpty()) {
            return 0;
        }
        $totalCreditHours = $sessionResults->sum('Total_Credit_Hours');
        $totalObtainedPoints = $sessionResults->sum('ObtainedCreditPoints');
        if ($totalCreditHours == 0) {
            return 0;
        }
        $CGPA = $totalObtainedPoints / $totalCreditHours;
        return round($CGPA, 2);
    }
    public function addOrCheckOfferedCourses(Request $request)
    {
        $validatedData = $request->validate([
            'courses' => 'required|array',
            'courses.*.course_id' => 'required|integer',
            'courses.*.session_id' => 'required|integer',
        ]);
        $courses = $validatedData['courses'];
        $status = [];
        foreach ($courses as $course) {
            // Check if course exists
            $courseRecord = Course::find($course['course_id']);
            if (!$courseRecord) {
                $status[] = [
                    'course_id' => $course['course_id'],
                    'session_id' => $course['session_id'],
                    'status' => 'failed',
                    'message' => 'Course not found.',
                ];
                continue;
            }

            // Check if offered course exists
            $exists = offered_courses::where('course_id', $course['course_id'])
                ->where('session_id', $course['session_id'])
                ->exists();

            if ($exists) {
                $status[] = [
                    'course_id' => $course['course_id'],
                    'session_id' => $course['session_id'],
                    'status' => 'exists',
                    'message' => 'Course already exists in the offered courses.'
                ];
            } else {
                offered_courses::create([
                    'course_id' => $course['course_id'],
                    'session_id' => $course['session_id']
                ]);

                $status[] = [
                    'course_id' => $course['course_id'],
                    'session_id' => $course['session_id'],
                    'status' => 'added',
                    'message' => 'Course successfully added to the offered courses.'
                ];
            }
        }

        // Return the response
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }
    public function addTeacherOfferedCourses(Request $request)
    {
        // Validate the input
        $validatedData = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.section_id' => 'required|integer',
            'data.*.teacher_id' => 'required|integer',
            'data.*.offered_course_id' => 'required|integer',
        ]);

        $status = []; // To store the result for each record

        foreach ($validatedData['data'] as $entry) {
            $sectionId = $entry['section_id'];
            $teacherId = $entry['teacher_id'];
            $offeredCourseId = $entry['offered_course_id'];

            // Check if section exists
            $section = Section::find($sectionId);
            if (!$section) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Section not found.',
                ];
                continue;
            }

            // Check if teacher exists
            $teacher = Teacher::find($teacherId);
            if (!$teacher) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Teacher not found.',
                ];
                continue;
            }

            // Check if offered course exists
            $offeredCourse = offered_courses::find($offeredCourseId);
            if (!$offeredCourse) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Offered Course not found.',
                ];
                continue;
            }

            // Check if a record already exists
            $existingRecord = teacher_offered_courses::where('section_id', $sectionId)
                ->where('offered_course_id', $offeredCourseId)
                ->first();

            if ($existingRecord) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $existingRecord->teacher_id,
                    'status' => 'Record already exists',
                ];
            } else {
                // Create the new record
                teacher_offered_courses::create([
                    'section_id' => $sectionId,
                    'teacher_id' => $teacherId,
                    'offered_course_id' => $offeredCourseId,
                ]);

                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'Record added successfully',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'statuses' => $status,
        ]);
    }
    public function updateOrInsertTeacherOfferedCourses(Request $request)
    {
        // Validate the input
        $validatedData = $request->validate([
            'data' => 'required|array|min:1',
            'data.*.section_id' => 'required|integer',
            'data.*.teacher_id' => 'required|integer',
            'data.*.offered_course_id' => 'required|integer',
        ]);

        $status = []; // To store the result for each record

        foreach ($validatedData['data'] as $entry) {
            $sectionId = $entry['section_id'];
            $teacherId = $entry['teacher_id'];
            $offeredCourseId = $entry['offered_course_id'];

            // Check if section exists
            $section = Section::find($sectionId);
            if (!$section) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Section not found.',
                ];
                continue;
            }

            // Check if teacher exists
            $teacher = Teacher::find($teacherId);
            if (!$teacher) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Teacher not found.',
                ];
                continue;
            }

            // Check if offered course exists
            $offeredCourse = offered_courses::find($offeredCourseId);
            if (!$offeredCourse) {
                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'failed',
                    'message' => 'Offered Course not found.',
                ];
                continue;
            }

            // Check if a record already exists
            $existingRecord = teacher_offered_courses::where('section_id', $sectionId)
                ->where('offered_course_id', $offeredCourseId)
                ->first();

            if ($existingRecord) {
                // Update the teacher_id
                $existingRecord->update(['teacher_id' => $teacherId]);

                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'Record updated successfully',
                ];
            } else {
                // Insert a new record
                teacher_offered_courses::create([
                    'section_id' => $sectionId,
                    'teacher_id' => $teacherId,
                    'offered_course_id' => $offeredCourseId,
                ]);

                $status[] = [
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId,
                    'teacher_id' => $teacherId,
                    'status' => 'Record added successfully',
                ];
            }
        }

        return response()->json([
            'success' => true,
            'statuses' => $status,
        ]);
    }
    public function assignJuniorLecturer(Request $request)
    {
        try {
            // Validate the input list of data
            $request->validate([
                'data' => 'required|array|min:1',
                'data.*.teacher_offered_course_id' => 'required|integer',
                'data.*.junior_lecturer_id' => 'required|integer',
            ]);

            // Initialize status arrays
            $status = [];
            $success = [];
            $faultyData = [];

            foreach ($request->input('data') as $entry) {
                $teacherOfferedCourseId = $entry['teacher_offered_course_id'];
                $juniorLecturerId = $entry['junior_lecturer_id'];

                // Check if Teacher Offered Course exists
                $teacherOfferedCourse = teacher_offered_courses::find($teacherOfferedCourseId);
                if (!$teacherOfferedCourse) {
                    $faultyData[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'failed',
                        'message' => "Teacher Offered Course with ID $teacherOfferedCourseId not found.",
                    ];
                    continue;
                }

                // Check if Junior Lecturer exists
                $juniorLecturer = JuniorLecturer::find($juniorLecturerId);
                if (!$juniorLecturer) {
                    $faultyData[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'failed',
                        'message' => "Junior Lecturer with ID $juniorLecturerId not found.",
                    ];
                    continue;
                }

                // Check if Junior Lecturer assignment already exists
                $existingAssignment = teacher_juniorlecturer::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('juniorlecturer_id', $juniorLecturerId)
                    ->first();

                if ($existingAssignment) {
                    $status[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'exists',
                        'message' => "Junior Lecturer with ID $juniorLecturerId is already assigned to Teacher Offered Course with ID $teacherOfferedCourseId.",
                    ];
                } else {
                    teacher_juniorlecturer::create([
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'juniorlecturer_id' => $juniorLecturerId,
                    ]);

                    $success[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'success',
                        'message' => "Assigned Junior Lecturer with ID $juniorLecturerId to Teacher Offered Course with ID $teacherOfferedCourseId.",
                    ];
                }
            }

            // Return response with success, failed, and status data
            return response()->json([
                'status' => 'success',
                'total_records' => count($request->input('data')),
                'added' => count($success),
                'failed' => count($faultyData),
                'faulty_data' => $faultyData,
                'success' => $success,
                'stat' => $status,
            ], 200);
        } catch (Exception $e) {
            // Handle unexpected errors
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function updateOrCreateTeacherJuniorLecturer(Request $request)
    {
        try {
            // Validate the incoming request data as an array of records
            $request->validate([
                'data' => 'required|array|min:1',  // Ensure 'data' is an array with at least one item
                'data.*.teacher_offered_course_id' => 'required|integer',  // Each item must have a valid teacher_offered_course_id
                'data.*.juniorlecturer_id' => 'required|integer',  // Each item must have a valid juniorlecturer_id
            ]);

            $status = [];
            $success = [];
            $faultyData = [];

            // Process each item in the 'data' array
            foreach ($request->input('data') as $entry) {
                $teacherOfferedCourseId = $entry['teacher_offered_course_id'];
                $juniorLecturerId = $entry['juniorlecturer_id'];

                // Check if the Teacher Offered Course exists
                $teacherOfferedCourse = teacher_offered_courses::find($teacherOfferedCourseId);
                if (!$teacherOfferedCourse) {
                    $faultyData[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'failed',
                        'message' => "Teacher Offered Course with ID $teacherOfferedCourseId not found.",
                    ];
                    continue;
                }

                // Check if the Junior Lecturer exists
                $juniorLecturer = JuniorLecturer::find($juniorLecturerId);
                if (!$juniorLecturer) {
                    $faultyData[] = [
                        'teacher_offered_course_id' => $teacherOfferedCourseId,
                        'junior_lecturer_id' => $juniorLecturerId,
                        'status' => 'failed',
                        'message' => "Junior Lecturer with ID $juniorLecturerId not found.",
                    ];
                    continue;
                }

                // Use updateOrCreate to either update or insert the record
                $teacherJuniorLecturer = teacher_juniorlecturer::updateOrCreate(
                    ['teacher_offered_course_id' => $teacherOfferedCourseId], // Lookup condition
                    ['juniorlecturer_id' => $juniorLecturerId] // Data to insert or update
                );

                $success[] = [
                    'teacher_offered_course_id' => $teacherOfferedCourseId,
                    'junior_lecturer_id' => $juniorLecturerId,
                    'status' => 'success',
                    'message' => "Teacher Junior Lecturer record has been successfully updated or created.",
                ];
            }

            return response()->json([
                'status' => 'success',
                'total_records' => count($request->input('data')),
                'added' => count($success),
                'failed' => count($faultyData),
                'faulty_data' => $faultyData,
                'success' => $success,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function assignGrader(Request $request)
    {
        try {
            $request->validate([
                'teacher_id' => 'required',
                'grader_id' => 'required',
            ]);

            $sessionId = (new session())->getCurrentSessionId();
            if ($sessionId == 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No Current Session Found! Try to Add the Grader From Add Option'
                ], 404);
            }

            $teacherId = $request->input('teacher_id');
            $graderId = $request->input('grader_id');

            // Check if Grader exists
            $grader = Grader::find($graderId);
            if (!$grader) {
                $grader = Grader::create([
                    'id' => $graderId,
                    'status' => 'active'
                ]);
                $status = "Grader created and set as active.";
            } else {
                $grader->update(['status' => 'active']);
                $status = "Grader updated and status set to active.";
            }

            // Check if Grader is already assigned to another Teacher in the same Session
            $existingAssignment = teacher_grader::where([
                'grader_id' => $graderId,
                'session_id' => $sessionId
            ])->exists();

            if ($existingAssignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => "The Grader is already assigned to another teacher in the given session.",
                    'grader_id' => $graderId,
                    'session_id' => $sessionId
                ], 400);
            }

            // Check if Grader is already assigned to the same Teacher in this Session
            $existingAssignmentForTeacher = teacher_grader::where([
                'grader_id' => $graderId,
                'teacher_id' => $teacherId,
                'session_id' => $sessionId
            ])->exists();

            if (!$existingAssignmentForTeacher) {
                teacher_grader::create([
                    'grader_id' => $graderId,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId,
                    'feedback' => ''
                ]);
                return response()->json([
                    'status' => 'success',
                    'message' => "Grader assigned to teacher successfully.",
                    'grader_id' => $graderId,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Grader is already assigned to the teacher for this session.",
                'grader_id' => $graderId,
                'teacher_id' => $teacherId,
                'session_id' => $sessionId
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
    public function AddGrader(Request $request)
    {
        try {
            $request->validate([
                'teacher_id' => 'required',
                'grader_id' => 'required',
                'session_id' => 'required', // Session ID is now coming from frontend
                'type' => 'nullable|in:merit,need-based' // Optional type validation
            ]);

            $teacherId = $request->input('teacher_id');
            $graderId = $request->input('grader_id');
            $sessionId = $request->input('session_id');
            $type = $request->input('type')??null; // Optional
            $status='in-active';
            if($sessionId===(new session())->getCurrentSessionId()){
                $status='active';
            }
            $grader = Grader::where('student_id', $graderId)->first();
            
            if (!$grader) {
                $grader = Grader::create([
                    'student_id' => $graderId,
                    'status' => $status,
                    'type'=>$type
                ]);
                $statusMessage = "Grader created and set as active.";
            } else {
                $grader->update(['status' => 'active']);
                $statusMessage = "Grader updated and status set to active.";
            }
            $graderId=$grader->id;
            // Check if the Grader is already assigned to a different Teacher in this session
            $existingAssignment = teacher_grader::where([
                'grader_id' => $graderId,
                'session_id' => $sessionId
            ])->exists();

            if ($existingAssignment) {
                return response()->json([
                    'status' => 'error',
                    'message' => "The Grader is already assigned to another teacher in the given session.",
                    'grader_id' => $graderId,
                    'session_id' => $sessionId
                ], 400);
            }

            // Check if Grader is already assigned to the same Teacher in this Session
            $existingAssignmentForTeacher = teacher_grader::where([
                'grader_id' => $graderId,
                'teacher_id' => $teacherId,
                'session_id' => $sessionId
            ])->exists();

            if (!$existingAssignmentForTeacher) {
                teacher_grader::create([
                    'grader_id' => $graderId,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId,
                    'feedback' => ''
                ]);
                return response()->json([
                    'status' => 'success',
                    'message' => "Grader assigned to teacher successfully.",
                    'grader_id' => $graderId,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId,
                    'type' => $type
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'message' => "Grader is already assigned to the teacher for this session.",
                'grader_id' => $graderId,
                'teacher_id' => $teacherId,
                'session_id' => $sessionId,
                'type' => $type
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'error' => $e->errors()
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


    public function addStudentEnrollment(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:student,id',
            'offered_course_id' => 'required|exists:offered_courses,id',
            'section_id' => 'required|exists:section,id',
        ]);

        $student_id = $validated['student_id'];
        $offered_course_id = $validated['offered_course_id'];
        $section_id = $validated['section_id'];

        // Check if the combination of student_id, offered_course_id, and section_id already exists
        $existingEnrollment = student_offered_courses::where('student_id', $student_id)
            ->where('offered_course_id', $offered_course_id)
            ->where('section_id', $section_id)
            ->first();

        if ($existingEnrollment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Enrollment already exists for this student in the given section.'
            ], 400);
        }

        // Check if the student is already enrolled in the course but in a different section
        $existingEnrollmentWithoutSection = student_offered_courses::where('student_id', $student_id)
            ->where('offered_course_id', $offered_course_id)
            ->first();

        if ($existingEnrollmentWithoutSection) {
            // Update the section_id
            $existingEnrollmentWithoutSection->update(['section_id' => $section_id]);
            return response()->json([
                'status' => 'success',
                'message' => 'Section updated for the student.'
            ], 200);
        }

        // Fetch all student enrollments
        $existingStudentCourses = student_offered_courses::where('student_id', $student_id)->get();

        // Fetch the course name of the given offered_course_id
        $newCourse = offered_courses::with('course')->find($offered_course_id);
        $newCourseName = $newCourse->course->code;

        $canReEnroll = false;

        // Check if any of the student's courses match the new course name and grade
        foreach ($existingStudentCourses as $enrollment) {
            $courseName = $enrollment->offeredCourse->course->code;

            if ($courseName === $newCourseName) {
                $grade = $enrollment->grade;
                if (in_array($grade, ['F', 'D'])) {
                    $enrollment->update([
                        'offered_course_id' => $offered_course_id,
                        'section_id' => $section_id
                    ]);
                    $canReEnroll = true;
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Student has Been Re-Enrolled in course '
                    ], 400);
                } else {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Student has passed the course and cannot be re-enrolled.'
                    ], 400);
                }
            }
        }

        // If no matching course found, simply insert a new record
        if (!$canReEnroll) {
            $newEnrollment = student_offered_courses::create([
                'student_id' => $student_id,
                'offered_course_id' => $offered_course_id,
                'section_id' => $section_id,
                'grade' => 'F', // Default grade could be 'F' or leave as NULL, depending on your business logic
                'attempt_no' => 1, // Set attempt_no to 1 initially
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Student successfully enrolled in the course.',
                'data' => $newEnrollment
            ], 201);
        }
    }

    public static function CompilerResult($offered_course_id, $section_id)
    {
        if (!$offered_course_id || !$section_id) {
            return;
        }
        $offered_course = offered_courses::with('course:id,name,credit_hours,lab')->find($offered_course_id);
        if (!$offered_course) {
            return;
        }
        $course = $offered_course->course;
        $isLab = $course->lab == 1;
        $students = student_offered_courses::where('section_id', $section_id)
            ->where('offered_course_id', $offered_course_id)
            ->with('student')
            ->get();
        $teacher_offered_c = teacher_offered_courses::where('offered_course_id', $offered_course_id)->where('section_id', $section_id)->first();
        if (!$teacher_offered_c) {
            return;
        }
        foreach ($students as $s) {
            $credit_hours = $course->credit_hours;
            $total_marks = match ($credit_hours) {
                1 => 20,
                2 => 40,
                3 => 60,
                4 => 80,
                default => 0,
            };
            $quality_points = match ($credit_hours) {
                1 => 4,
                2 => 8,
                3 => 12,
                4 => 16,
                default => 0,
            };
            $mid_marks = self::CountExamMarks('Mid', $s->student->id, $teacher_offered_c->id);
            $final_marks = self::CountExamMarks('Final', $s->student->id, $teacher_offered_c->id);
            $lab_marks = $isLab ? 0 : null;
            if ($isLab) {
                $lab_marks = self::getLabTaskResult($s->student->id, $teacher_offered_c->id);
            }
            $internal_marks = self::compileInternalMarks($s->student->id, $teacher_offered_c->id, $total_marks == 80 ? 12 : (($total_marks == 60 && $isLab) ? 8 : 12));
            $obtained_marks = $internal_marks + $mid_marks + $final_marks + ($isLab ? $lab_marks : 0);
            $theorymarks = $total_marks;
            if ($isLab) {
                $theorymarks = $theorymarks - 20;
            }
            $theory_per = ($internal_marks + $mid_marks + $final_marks) > 0 ? (($internal_marks + $mid_marks + $final_marks) / $theorymarks) * 100 : 0;
            $percentage = ($total_marks > 0) ? ($obtained_marks / $total_marks) * 100 : 0;
            $grade = 'F';
            if ($isLab && $lab_marks < 8) {
                $grade = 'F';
                $quality_points = 0;
            } elseif (!$isLab && $percentage < 40) {
                $grade = 'F';
                $quality_points = 0;
            } else if ($isLab && $theory_per < 40) {
                $grade = 'F';
                $quality_points = 0;
            } else {
                if ($percentage >= 85) {
                    $grade = 'A';
                } elseif ($percentage >= 75) {
                    $grade = 'B';
                    $quality_points == 3 ? 10 : 14;
                } elseif ($percentage >= 55) {
                    $grade = 'C';
                    $quality_points == 3 ? 9 : 12;
                } elseif ($percentage >= 40) {
                    $grade = 'D';
                    $quality_points == 3 ? 7 : 10;
                } else {
                    $grade = 'F';
                    $quality_points = 0;
                }
            }
            $existingResult = subjectresult::where('student_offered_course_id', $s->id)->first();
            if ($existingResult) {
                $existingResult->update([
                    'mid' => $mid_marks,
                    'internal' => $internal_marks,
                    'final' => $final_marks,
                    'lab' => $lab_marks,
                    'grade' => $grade,
                    'quality_points' => $quality_points,
                ]);
            } else {
                subjectresult::create([
                    'student_offered_course_id' => $s->id,
                    'mid' => $mid_marks,
                    'internal' => $internal_marks,
                    'final' => $final_marks,
                    'lab' => $lab_marks,
                    'grade' => $grade,
                    'quality_points' => $quality_points,
                ]);
            }
            $s->update(['grade' => $grade]);
            $GPA = self::calculateAndStoreGPA($s->student_id, $offered_course->session_id);
            $CGPA = self::calculateAndUpdateCGPA($s->student_id);
            if ($s->student->id == 36) {
                return [
                    "Student Name" => $s->student->name,
                    "Subject Name" => $course->name,
                    "Mid Marks" => $mid_marks,
                    "Final" => $final_marks,
                    "Lab Marks" => $lab_marks,
                    "Internal Marks" => $internal_marks,
                    "Grade" => $grade,
                    "Quality Points" => $quality_points,
                    "Total Marks of Subject" => $total_marks,
                    "Obtained Total" => $obtained_marks,
                    "Percentage of Theory" => $theory_per,
                    "Percentage of Total" => $percentage,
                    "Updated CGPA" => $CGPA,
                    "Updated GPA" => $GPA
                ];
            }

        }
    }

    public static function CountExamMarks($type, $student_id, $teacher_offered_course_id)
    {
        if (!student::find($student_id)) {
            return 0;
        }
        $teacherOfferedCourse = teacher_offered_courses::with(['offeredCourse'])->find($teacher_offered_course_id);
        if (!$teacherOfferedCourse) {
            return 0;
        }
        $offeredCourseId = $teacherOfferedCourse->offeredCourse->id;
        $studentId = $student_id;
        $exam = exam::where('offered_course_id', $offeredCourseId)->where('type', $type)->with(['questions'])->first();

        if (!$exam) {
            return 0;
        }
        $totalObtainedMarks = 0;
        $totalMarks = 0;
        $solidMarks = 0;
        $examTotalObtained = 0;
        foreach ($exam->questions as $question) {
            $result = student_exam_result::where('question_id', $question->id)
                ->where('student_id', $studentId)
                ->where('exam_id', $exam->id)
                ->first();
            if ($result) {
                $examTotalObtained += $result->obtained_marks;
            }
        }
        $obtained_marks = $examTotalObtained;
        $solid_marks_equivalent = $exam->Solid_marks > 0
            ? ($examTotalObtained / $exam->total_marks) * $exam->Solid_marks
            : 0;
        return $solid_marks_equivalent ?? 0;
    }



    public static function SingleSubjectAttendance($student_id = null, $teacher_offered_course_id = null)
    {
        if (!$student_id || !$teacher_offered_course_id) {
            return 0;
        }
        try {
            $total_classes = attendance::where('teacher_offered_course_id', $teacher_offered_course_id)
                ->distinct('date_time')
                ->count('date_time');
            $total_present = attendance::where('teacher_offered_course_id', $teacher_offered_course_id)
                ->where('student_id', $student_id)
                ->where('status', 'p')
                ->count();
            $percentage = ($total_classes > 0) ? ($total_present / $total_classes) * 100 : 0;
            return $percentage;
        } catch (Exception $ex) {
            return 0;
        }
    }
    public static function getTotalAndObtainedMarks($studentId, $teacherOfferedCourseId)
    {
        $taskConsideration = task_consideration::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->whereIn('type', ['Quiz', 'Assignment'])
            ->get();

        $totalMarks = 0;
        $obtainedMarks = 0;
        $taskTypes = ['Quiz', 'Assignment'];

        foreach ($taskTypes as $type) {
            $teacherTasks = [];
            $juniorTasks = [];
            $consideration = $taskConsideration->where('type', $type)->first();

            if ($consideration) {
                $top = $consideration->top;
                $jlConsiderCount = $consideration->jl_consider_count;
                if ($jlConsiderCount === null) {
                    $tasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                        ->where('type', $type)
                        ->get();
                    $teacherTasks = $tasks->sortByDesc(function ($task) use ($studentId) {
                        return student_task_result::where('Task_id', $task->id)
                            ->where('Student_id', $studentId)
                            ->first()->ObtainedMarks ?? 0;
                    })->take($top);
                } else {
                    $teacherCount = $top - $jlConsiderCount;
                    $jlCount = $jlConsiderCount;

                    $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                        ->where('CreatedBy', 'Teacher')
                        ->where('type', $type)
                        ->get()
                        ->sortByDesc(function ($task) use ($studentId) {
                            return student_task_result::where('Task_id', $task->id)
                                ->where('Student_id', $studentId)
                                ->first()->ObtainedMarks ?? 0;
                        })->take($teacherCount);

                    $juniorTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                        ->where('CreatedBy', 'JuniorLecturer')
                        ->where('type', $type)
                        ->get()
                        ->sortByDesc(function ($task) use ($studentId) {
                            return student_task_result::where('Task_id', $task->id)
                                ->where('Student_id', $studentId)
                                ->first()->ObtainedMarks ?? 0;
                        })->take($jlCount);
                }
            } else {
                // If no consideration exists, fetch all tasks of that type
                $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                    ->where('type', $type)
                    ->get();
            }

            // Sum up the total and obtained marks
            foreach ($teacherTasks->merge($juniorTasks) as $task) {
                $taskTotalMarks = $task->points ?? 0;
                $taskObtainedMarks = student_task_result::where('Task_id', $task->id)
                    ->where('Student_id', $studentId)
                    ->first()->ObtainedMarks ?? 0;

                $totalMarks += $taskTotalMarks;
                $obtainedMarks += $taskObtainedMarks;
            }
        }

        return [
            'status' => 'success',
            'teacher_offered_course_id' => $teacherOfferedCourseId,
            'total_marks' => $totalMarks,
            'obtained_marks' => $obtainedMarks,
        ];
    }
    public static function compileInternalMarks($studentId, $teacherOfferedCourseId, $internalMarks)
    {
        $attendance = self::SingleSubjectAttendance($studentId, $teacherOfferedCourseId);
        $attendancePercentage = $attendance ?? 0;
        $taskMarks = self::getTotalAndObtainedMarks($studentId, $teacherOfferedCourseId);
        $totalMarks = $taskMarks['total_marks'];
        $obtainedMarks = $taskMarks['obtained_marks'];
        $attendanceMarks = 0;
        $taskBasedMarks = 0;
        if ($internalMarks == 8) {
            $attendanceMarksWeight = 2;
        } elseif ($internalMarks == 12) {
            $attendanceMarksWeight = 4;
        } else {
            return 0;
        }
        if ($attendancePercentage >= 80) {
            $attendanceMarks = ($attendancePercentage / 100) * $attendanceMarksWeight;
        } else {
            $attendanceMarks = 0;
        }
        $remainingMarksForTasks = $internalMarks - $attendanceMarksWeight;
        if ($totalMarks > 0) {
            $taskBasedMarks = ($obtainedMarks / $totalMarks) * $remainingMarksForTasks;
        }
        return min($attendanceMarks + $taskBasedMarks, $internalMarks) ?? 0;
    }
    public static function getLabTaskResult($studentId, $teacherOfferedCourseId)
    {
        $taskConsideration = task_consideration::where('teacher_offered_course_id', $teacherOfferedCourseId)
            ->where('type', 'LabTask')
            ->first();
        $teacherTasks = [];
        $juniorTasks = [];

        if ($taskConsideration) {
            // Step 2: Fetch Considered LabTasks
            $top = $taskConsideration->top;
            $jlConsiderCount = $taskConsideration->jl_consider_count;
            $teacherCount = $top;
            $jlCount = 0;

            if ($jlConsiderCount !== null) {
                $teacherCount = $top - $jlConsiderCount;
                $jlCount = $jlConsiderCount;
            }

            // Fetch Teacher Created LabTasks
            $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->where('CreatedBy', 'Teacher')
                ->where('type', 'LabTask')
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
                ->where('type', 'LabTask')
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

        } else {
            $teacherTasks = task::where('teacher_offered_course_id', $teacherOfferedCourseId)
                ->where('type', 'LabTask')
                ->get()
                ->map(function ($task) use ($studentId) {
                    $obtainedMarks = student_task_result::where('Task_id', $task->id)
                        ->where('Student_id', $studentId)
                        ->first()->ObtainedMarks ?? 0;
                    $task->obtained_marks = $obtainedMarks;
                    return $task;
                });
        }
        $totalMarks = collect($teacherTasks)->sum('points') + collect($juniorTasks)->sum('points');

        $obtainedMarks = collect($teacherTasks)->sum('obtained_marks') + collect($juniorTasks)->sum('obtained_marks');
        $labTaskMarks = 0;
        if ($totalMarks > 0) {
            $labTaskMarks = ($obtainedMarks / $totalMarks) * 20;
        }
        return round($labTaskMarks, 0);
    }
    public function Sample(Request $request)
    {
        try {

            return response()->json(
                [
                    // 'atteandance per' => self::SingleSubjectAttendance(36, 64),
                    // 'Inernal Marks' => self::compileInternalMarks(36, 64, 12),
                    // 'Lab Marks' => self::getLabTaskResult(36, 64)
                    "REUSLT" => self::CompilerResult(18, 16)
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
