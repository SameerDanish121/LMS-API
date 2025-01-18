<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\dayslot;
use App\Models\FileHandler;
use App\Models\grader;
use App\Models\juniorlecturer;
use App\Models\program;
use App\Models\StudentManagement;
use App\Models\teacher;
use App\Models\teacher_grader;
use App\Models\teacher_juniorlecturer;
use Exception;
use GrahamCampbell\ResultType\Success;
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
use DateTime;
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\select;
class DatacellController extends Controller
{

    public function Archives(Request $request)
    {
        try {
            if ($request->directory) {
                $directory_details = FileHandler::getFolderInfo($request->directory);
                if (!$directory_details) {
                    throw new Exception('No Directory Exsist with the Given Name');
                }
            } else {
                $directory_details = FileHandler::getFolderInfo();
            }
            return response()->json(
                [
                    'message' => 'Directory Details Fetched !',
                    'Details' => $directory_details,
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
    public function DeleteFolderByPath(Request $request)
    {
        try {
            if ($request->path) {
                $isFolderDeleted = FileHandler::deleteFolder($request->path);
                if (!$isFolderDeleted) {
                    throw new Exception('No Directory Exsist with the Given Path');
                }
            } else {
                throw new Exception('Folder PATH OR Name is Required , Please Select Valid Folder !');
            }
            return response()->json(
                [
                    'message' => 'Folder Deleted Successfully !',
                    'logs' => " Deleted {$isFolderDeleted} Of Data  "
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
                } else {
                    $imageContent = file_get_contents(public_path($originalPath));
                    $student->image = base64_encode($imageContent);
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
    public function OfferedCourseTeacheruploadExcel(Request $request)
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
                    $section_id = section::addNewSection($row['A']);
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
    public function UploadStudentEnrollments(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
                'session' => 'required'
            ]);
            $session = (new session())->getSessionIdByName($request->session);
            if (!$session) {
                throw new Exception('No Session Exsist with given name');
            }
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $NonEmptyRow = [];
            foreach ($sheetData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    $row = array_map('trim', $row);
                    $NonEmptyRow[] = $row;
                }
            }
            $response = [

            ];

            array_shift($NonEmptyRow);
            $RowNo = 1;
            foreach ($NonEmptyRow as $singleRow) {
                $studentRegNo = $singleRow['A'];
                $studentName = $singleRow['B'];
                $enrollmentExsist = null;
                $sectionExsist = null;
                $student_id = student::where('RegNo', $studentRegNo)->value('id');
                if (!$student_id) {
                    $response[] = ['status' => "error on Row No {$RowNo}", 'message' => "No Student with this RegNo ={$studentRegNo} , Name = {$studentName} Found in Student Record"];
                    continue;
                }
                $iteration = 0;
                $CellNo = 0;
                foreach ($singleRow as $cell) {
                    $CellNo++;
                    $iteration++;
                    if ($iteration <= 2) {
                        continue;
                    }
                    if ($cell != null || $cell != '') {
                        if ($enrollmentExsist) {
                            $sectionExsist = $cell;
                            $section_id = section::addNewSection($sectionExsist);
                            if (!$section_id) {
                                $response[] = ['status' => "error on Row No " . ($RowNo - 1) . " , Cell NO {$CellNo}", 'message' => "The Data {$sectionExsist} is in Incorrect Format "];
                                $enrollmentExsist = null;
                                $sectionExsist = null;
                                continue;
                            }
                            $courseInfo = explode('_', $enrollmentExsist);
                            if (count($courseInfo) == 2) {
                                $course_name = $courseInfo[0];
                                $course_code = $courseInfo[1];
                            } else {
                                $response[] = ['status' => "error on Row No {$RowNo} , Cell NO {$CellNo}", 'message' => "The Data {$courseInfo} is in Incorrect Format"];
                                $enrollmentExsist = null;
                                $sectionExsist = null;
                                continue;
                            }

                            $course_id = (new course())->getIDByName($course_name);
                            if (!$course_id) {
                                $response[] = ['status' => "error on Row No {$RowNo} , Cell NO {$CellNo}", 'message' => "The Course {$course_name} is Not in Course Record"];
                                $enrollmentExsist = null;
                                $sectionExsist = null;
                                continue;
                            }
                            $offered_course_id = offered_courses::where('session_id', $session)
                                ->where('course_id', $course_id)->value('id');
                            if (!$offered_course_id) {
                                $response[] = ['status' => "error on Row No {$RowNo} , Cell NO {$CellNo}", 'message' => "The Course {$course_name} is Not Yet offered in Session {$request->session}"];
                                $enrollmentExsist = null;
                                $sectionExsist = null;
                                continue;
                            }
                            $check = self::EnrollorReEnroll($student_id, $course_id, $offered_course_id, $section_id);
                            if ($check['message'] == 'success') {
                                $response[] = ['status' => $check['status'], 'message' => " The Enrollment for {$studentRegNo}/{$studentName} in {$course_name}: Cell No {$CellNo}, Row NO {$RowNo} " . $check['message']];
                            } else {
                                $response[] = ['status' => $check['status'], 'message' => " The Enrollment for {$studentRegNo}/{$studentName} in {$course_name}: Cell No {$CellNo}, Row NO {$RowNo} " . $check['message']];
                            }
                            $enrollmentExsist = null;
                            $sectionExsist = null;
                        } else {
                            $enrollmentExsist = $cell;

                        }
                    } else {
                        continue;
                    }
                }

            }
            $successMessages = [];
            $errorMessages = [];
            foreach ($response as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }
            return response()->json(
                [
                    'Message' => 'Data Inserted Successfully !',
                    'data' => [
                        "Total Student Rows"=>count($NonEmptyRow),
                        "Added Enrollments"=>count($successMessages),
                        "Failed Enrollments"=>count($errorMessages),
                        "Sucess" => $successMessages,
                        "Error" => $errorMessages
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
    public static function EnrollorReEnroll($student_id, $course_id, $offered_course_id, $section_id)
    {
        try {
            if (!$student_id || !$course_id || !$offered_course_id || !$section_id) {
                return ['status' => 'failure', 'message' => 'Invalid parameters'];
            }
            $existingRecord = student_offered_courses::where('student_id', $student_id)
                ->where('offered_course_id', $offered_course_id)
                ->first();
            if ($existingRecord) {
                return ['status' => 'success', 'message' => 'Exist'];
            }
            $offeredCoursesList = offered_courses::where('course_id', $course_id)->pluck('id')->toArray();
            $studentCourse = student_offered_courses::where('student_id', $student_id)
                ->whereIn('offered_course_id', $offeredCoursesList)
                ->first();
            if ($studentCourse) {
                if (in_array($studentCourse->grade, ['F', 'D'])) {
                    $studentCourse->offered_course_id = $offered_course_id;
                    $studentCourse->attempt_no += 1;
                    $studentCourse->grade = null;
                    $studentCourse->section_id = $section_id;
                    $studentCourse->save();
                    return ['status' => 'Re-Enrolled', 'message' => "Re-Enrolled the the Course with {$studentCourse->grade}"];
                }
            }
            student_offered_courses::create([
                'student_id' => $student_id,
                'offered_course_id' => $offered_course_id,
                'section_id' => $section_id,
                'attempt_no' => 0,
                'grade' => null,
            ]);
            return ['status' => 'success', 'message' => 'Record inserted successfully'];
        } catch (Exception $ex) {
            return ['status' => 'error', 'message' => "{$ex->getMessage()}"];
        }
    }


    ///////////////////////////////////////////////////////UNDER TEST///////////////////
    public function getTimetableGroupedBySection(Request $request)
    {
        $session_id = $request->session_id ?? (new Session())->getCurrentSessionId();
        if (!$session_id) {
            return response()->json(['error' => 'Session ID is required.'], 400);
        }
        $timetable = Timetable::with([
            'course:name,id,description',
            'teacher:name,id',
            'venue:venue,id',
            'dayslot:day,start_time,end_time,id',
            'juniorLecturer:id,name',
        ])
            ->where('session_id', $session_id)
            ->get()
            ->map(function ($item) {
                return [
                    'section' => (new Section())->getNameByID($item->section_id) ?? 'N/A',
                    'name' => $item->course->name ?? 'N/A',
                    'description' => $item->course->description ?? 'N/A',
                    'teacher' => $item->teacher->name ?? 'N/A',
                    'junior_lecturer' => $item->juniorLecturer->name ?? 'N/A',
                    'venue' => $item->venue->venue ?? 'N/A',
                    'day' => $item->dayslot->day ?? 'N/A',
                    'time' => ($item->dayslot->start_time && $item->dayslot->end_time)
                        ? Carbon::parse($item->dayslot->start_time)->format('g:i A') . ' - ' . Carbon::parse($item->dayslot->end_time)->format('g:i A')
                        : 'N/A',
                ];
            })
            ->groupBy('section');

        return response()->json([
            "status" => "Successfull",
            "timetable" => $timetable,
        ], 200);
    }
    public function AddOrUpdateStudent(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls'
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $filteredData = [];
            foreach ($sheetData as $row) {
                $filteredData[] = array_slice($row, 0, 11);
            }
            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    if ($row['A'] != 'RegNo') {
                        $row = array_map('trim', $row);
                        $nonEmpty[] = $row;
                    }
                }
            }
            $RowNo = 1;
            $status = [];
            foreach ($nonEmpty as $singleRow) {
                $RowNo++;
                $regNo = $singleRow['A'];
                $Name = $singleRow['B'];
                $gender = $singleRow['C'];
                $dob = $singleRow['D'];
                $guardian = $singleRow['E'];
                $cgpa = $singleRow['F'];
                $email = $singleRow['G'];
                $currentSection = $singleRow['H'];
                $status = $singleRow['I'];
                $InTake = $singleRow['J'];
                $Discipline = $singleRow['K'];
                $dob = (new DateTime($dob))->format('Y-m-d');
                $password = Action::generateUniquePassword($Name);
                $user_id = Action::addOrUpdateUser($regNo, $password, $email, 'Student');
                $section_id = section::addNewSection($currentSection);

                if (!$section_id) {
                    $status[] = ["status" => 'failed', "reason" => "The Field Current Section {$currentSection} Format is Not Correct !"];
                }
                $session_id = (new session())->getSessionIdByName($InTake);
                if (!$session_id) {
                    $status[] = ["status" => 'failed', "reason" => "The Field Current Session  {$InTake} Format is Not Correct !"];
                }
                $program_id = program::where('name', $Discipline)->value('id');
                if (!$program_id) {
                    $status[] = ["status" => 'failed', "reason" => "The Field Disciplein  {$Discipline} Format is Not Correct !"];
                }
                $student = StudentManagement::addOrUpdateStudent($regNo, $Name, $cgpa, $gender, $dob, $guardian, null, $user_id, $section_id, $program_id, $session_id, $status);

                if ($student) {
                    $status[] = ["status" => 'success', "Logs" => "The Student with RegNo : {$regNo} ,Name : {$Name}  is added to Record !", "Username" => $regNo, "Password" => $password];
                } else {
                    $status[] = ["status" => 'failed', 'Reason' => 'Unknown'];
                }

            }
            $successMessages = [];
            $errorMessages = [];
            foreach ($status as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }
            return response()->json(
                [
                    'Message' => 'Data Inserted Successfully !',
                    'success' => $successMessages,
                    'failed' => $errorMessages
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
    public function AddOrUpdateCourses(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls'
            ]);

            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $filteredData = [];
            foreach ($sheetData as $row) {
                $filteredData[] = array_slice($row, 0, 8); // Fetch only necessary columns
            }

            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    if ($row['A'] != 'code') {
                        $row = array_map('trim', $row);
                        $nonEmpty[] = $row;
                    }
                }
            }

            $RowNo = 1;
            $status = [];

            foreach ($nonEmpty as $singleRow) {
                $RowNo++;

                $code = $singleRow['A'];
                $name = $singleRow['B'];
                $creditHours = $singleRow['C'];
                $preReqMain = $singleRow['D'] === 'Null' ? null : $singleRow['D'];
                $program = $singleRow['E'];
                $type = $singleRow['F'];
                $shortform = $singleRow['G']; // Map to 'description'
                $lab = $singleRow['H'];

                // Fetch the program ID
                $programId = Program::where('name', $program)->value('id');

                if (!$programId) {
                    $status[] = ["status" => 'failed', "reason" => "The Field Program {$program} does not exist!"];
                    continue;
                }

                // Fetch the prerequisite course ID if applicable
                $preReqId = null;
                if ($preReqMain !== null) {
                    $preReqId = Course::where('name', $preReqMain)->value('id');
                    if (!$preReqId) {
                        $status[] = ["status" => 'failed', "reason" => "The prerequisite course {$preReqMain} does not exist!"];
                        continue;
                    }
                }

                // Check if the course already exists
                $course = Course::where('name', $name)->where('code', $code)->first();

                if ($course) {
                    $course->update([
                        'code' => $code,
                        'credit_hours' => $creditHours,
                        'pre_req_main' => $preReqId,
                        'program_id' => $programId,
                        'type' => $type,
                        'description' => $shortform,
                        'lab' => $lab,
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The course with Name: {$name} was updated."];
                } else {
                    Course::create([
                        'code' => $code,
                        'name' => $name,
                        'credit_hours' => $creditHours,
                        'pre_req_main' => $preReqId,
                        'program_id' => $programId,
                        'type' => $type,
                        'description' => $shortform,
                        'lab' => $lab,
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The course with Name: {$name} was added."];
                }
            }

            $successMessages = [];
            $errorMessages = [];
            foreach ($status as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }

            return response()->json(
                [
                    'Message' => 'Courses Processed Successfully!',
                    'success' => $successMessages,
                    'failed' => $errorMessages
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
    public function AddOrUpdateTeachers(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls'
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $filteredData = [];
            foreach ($sheetData as $row) {
                $filteredData[] = array_slice($row, 0, 4); // Fetch only necessary columns
            }

            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    if ($row['A'] != 'Name') {
                        $row = array_map('trim', $row);
                        $nonEmpty[] = $row;
                    }
                }
            }

            $RowNo = 1;
            $status = [];

            foreach ($nonEmpty as $singleRow) {
                $RowNo++;

                $name = $singleRow['A'];
                $dateOfBirth = $singleRow['B'];
                $gender = $singleRow['C'];
                $email = $singleRow['D'];
                $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
                $userExists = User::where('username', $username)->exists();

                if ($userExists) {
                    $status[] = ["status" => 'failed', "reason" => "Username {$username} already exists!"];
                    continue;
                }
                $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
                $password = Action::generateUniquePassword($name);
                $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');

                if (!$userId) {
                    $status[] = ["status" => 'failed', "reason" => "Failed to create or update user for {$name}."];
                    continue;
                }
                $teacher = Teacher::where('name', $name)->first();

                if ($teacher) {
                    $teacher->update([
                        'user_id' => $userId,
                        'name' => $name,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The teacher with Name: {$name} was updated."];
                } else {
                    Teacher::create([
                        'user_id' => $userId,
                        'name' => $name,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The teacher with Name: {$name} was added."];
                }
            }

            $successMessages = [];
            $errorMessages = [];
            foreach ($status as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }

            return response()->json(
                [
                    'Message' => 'Teachers Processed Successfully!',
                    'success' => $successMessages,
                    'failed' => $errorMessages
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
    public function assignGrader(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
                'session_name' => 'required|string'
            ]);

            $file = $request->file('excel_file');
            $sessionName = $request->input('session_name');

            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $filteredData = [];
            foreach ($sheetData as $row) {
                $filteredData[] = array_slice($row, 0, 5); // Fetch relevant columns
            }

            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    if ($row['A'] != 'RegNo') {
                        $row = array_map('trim', $row);
                        $nonEmpty[] = $row;
                    }
                }
            }

            $sessionId = (new Session())->getSessionIdByName($sessionName);

            if (!$sessionId) {
                return response()->json([
                    'status' => 'failed',
                    'message' => "Invalid session name: {$sessionName}"
                ], 400);
            }

            // Set all grader statuses to inactive
            Grader::query()->update(['status' => 'in-active']);

            $status = [];

            foreach ($nonEmpty as $row) {
                $regNo = $row['A'];
                $name = $row['D'];
                $type = $row['E'];
                $studentId = Student::where('RegNo', $regNo)->value('id');

                if (!$studentId) {
                    $status[] = ["status" => 'failed', "reason" => "Student with RegNo {$regNo} not found."];
                    continue;
                }
                $grader = Grader::where('student_id', $studentId)->first();

                if ($grader) {
                    $grader->update([
                        'type' => $type,
                        'status' => 'active'
                    ]);
                } else {
                    // Insert new grader
                    $grader = grader::create([
                        'student_id' => $studentId,
                        'type' => $type,
                        'status' => 'active'
                    ]);
                }

                $teacherId = Teacher::where('name', $name)->value('id');

                if (!$teacherId) {
                    $status[] = ["status" => 'failed', "reason" => "Teacher with name {$name} not found."];
                    continue;
                }

                // Check if the teacher_grader record exists
                $teacherGrader = teacher_grader::where([
                    'grader_id' => $grader->id,
                    'teacher_id' => $teacherId,
                    'session_id' => $sessionId
                ])->first();

                if (!$teacherGrader) {
                    // Insert new teacher_grader record
                    teacher_grader::create([
                        'grader_id' => $grader->id,
                        'teacher_id' => $teacherId,
                        'session_id' => $sessionId,
                        'feedback' => '' // Insert empty string as feedback if none provided
                    ]);
                }

                $status[] = ["status" => 'success', "message" => "Grader assigned for RegNo {$regNo}.", "teacher" => $name, "session" => $sessionName];
            }

            $successMessages = [];
            $errorMessages = [];
            foreach ($status as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }

            return response()->json([
                'Message' => 'Grader assignment processed.',
                'success' => $successMessages,
                'failed' => $errorMessages
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
    public function assignJuniorLecturer(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
                'session_name' => 'required|string'
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

            $sessionName = $request->input('session_name');
            $sessionId = (new Session())->getSessionIdByName($sessionName);

            if (!$sessionId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid session name.'
                ], 400);
            }

            $status = [];
            foreach ($sheetData as $row) {
                if (trim($row['A']) === 'Section') {
                    continue;
                }

                $sectionName = trim($row['A']);
                $courseTitle = trim($row['B']);
                $juniorLecturerName = trim($row['C']);
                $teacherName = trim($row['D']);
                $courseId = Course::where('name', $courseTitle)->value('id');
                if (!$courseId) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Course '$courseTitle' not found."
                    ];
                    continue;
                }
                $offeredCourseId = offered_courses::where('course_id', $courseId)
                    ->where('session_id', $sessionId)
                    ->value('id');

                if (!$offeredCourseId) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Offered course for '$courseTitle' not found in session '$sessionName'."
                    ];
                    continue;
                }

                // Fetch section_id
                $sectionId = (new Section())->getNameByID($sectionName);
                if (!$sectionId) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Section '$sectionName' not found."
                    ];
                    continue;
                }
                $teacherId = Teacher::where('name', $teacherName)->value('id');
                if (!$teacherId) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Teacher '$teacherName' not found."
                    ];
                    continue;
                }
                $teacherOfferedCourse = teacher_offered_courses::where([
                    'teacher_id' => $teacherId,
                    'section_id' => $sectionId,
                    'offered_course_id' => $offeredCourseId
                ])->first();

                if (!$teacherOfferedCourse) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Teacher Offered Course not found for Teacher '$teacherName', Course '$courseTitle', Section '$sectionName'."
                    ];
                    continue;
                }
                $juniorLecturerId = juniorlecturer::where('name', $juniorLecturerName)->value('id');
                if (!$juniorLecturerId) {
                    $status[] = [
                        'status' => 'failed',
                        'message' => "Junior Lecturer '$juniorLecturerName' not found."
                    ];
                    continue;
                }
                $teacherJuniorLecturer = teacher_juniorlecturer::updateOrCreate(
                    [
                        'teacher_offered_course_id' => $teacherOfferedCourse->id,
                    ],
                    [
                        'juniorlecturer_id' => $juniorLecturerId
                    ]
                );

                $status[] = [
                    'status' => 'success',
                    'message' => "Assigned Junior Lecturer '$juniorLecturerName' for course '$courseTitle' in section '$sectionName'."
                ];
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Operation completed.',
                'details' => $status
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while processing the request.',
                'error' => $e->getMessage()
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
                $data['TL_sender_id'] = $request->TL_sender_id ?? 113;
            } elseif ($sender === 'Admin' || $sender === 'Datacell') {
                $data['TL_sender_id'] = $request->TL_sender_id ?? null;
            } else {
                return response()->json(['message' => 'Invalid sender role.'], 400);
            }

            $notification = notification::create($data);

            return response()->json(['message' => 'Notification sent successfully!', 'data' => $notification], 201);

        } catch (Exception $e) {
            return response()->json(['message' => 'Failed to send notification.', 'error' => $e->getMessage()], 500);
        }
    }
    /////////////////////////////////////////////////////////////EXCEL~UPLOADING/////////////////////////////////
    //////////////Improved Code////////////
    public function UploadTimetableExcel(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
                'session' => 'required'
            ]);

            $session_name = $request->session;
            $session_id = (new session())->getSessionIdByName($session_name);
            if (!$session_id) {
                $session_id = (new session())->getUpcomingSessionId();
            }
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $NonEmptyRow = [];
            foreach ($sheetData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    $row = array_map('trim', $row);
                    $NonEmptyRow[] = $row;
                }
            }
            $sectionList = [];
            foreach ($NonEmptyRow[0] as $firstRowCell) {
                if ($firstRowCell != null || $firstRowCell != '') {
                    $sectionList[] = $firstRowCell;
                }
            }
            $ColumnEmptyCheck = [];
            $sliceLength = count($sectionList);
            foreach ($NonEmptyRow as $row) {
                $slicedRow = array_slice($row, 0, $sliceLength);
                $ColumnEmptyCheck[] = $slicedRow;
            }
            $Monday = [];
            $Tuesday = [];
            $Wednesday = [];
            $Thursday = [];
            $Firday = [];
            $WhatIs = '';
            foreach ($ColumnEmptyCheck as $row) {
                if ($row['A'] == 'Monday') {
                    $WhatIs = 'Monday';
                    continue;
                } else if ($row['A'] == 'Tuesday') {
                    $WhatIs = 'Tuesday';
                    continue;
                } else if ($row['A'] == 'Wednesday') {
                    $WhatIs = 'Wednesday';
                    continue;
                } else if ($row['A'] == 'Thursday') {
                    $WhatIs = 'Thursday';
                    continue;
                } else if ($row['A'] == 'Friday') {
                    $WhatIs = 'Friday';
                    continue;
                } else {
                    if ($WhatIs == 'Monday') {
                        $Monday[] = $row;
                    }
                    if ($WhatIs == 'Tuesday') {
                        $Tuesday[] = $row;
                    }
                    if ($WhatIs == 'Wednesday') {
                        $Wednesday[] = $row;
                    }
                    if ($WhatIs == 'Thursday') {
                        $Thursday[] = $row;
                    }
                    if ($WhatIs == 'Friday') {
                        $Firday[] = $row;
                    }
                }
            }
            function convertTo24HourFormat($time)
            {
                return date("H:i:s", strtotime($time));
            }
            $Success = [];
            $Error = [];
            foreach ($Monday as $MondayRows) {
                $Time = $MondayRows['A'];
                $Day = 'Monday';
                list($startTime, $endTime) = explode(' - ', $Time);
                $startTimeFormatted = convertTo24HourFormat($startTime);
                $endTimeFormatted = convertTo24HourFormat($endTime);

                $dayslot = dayslot::firstOrCreate(
                    [
                        'day' => $Day,
                        'start_time' => $startTimeFormatted,
                        'end_time' => $endTimeFormatted,
                    ]
                );

                $dayslotId = $dayslot->id;

                foreach ($MondayRows as $index => $value) {

                    if ($value == $Time) {
                        continue;
                    } else if ($value != null && $value != '') {
                        $timetable = Action::insertOrCreateTimetable($value, $dayslotId);
                        if ($timetable) {
                            if ($timetable['status'] == 'error'){
                                $Error[] = $timetable;
                            }else{
                                $Success[] = $timetable;
                            }
                        } else if ($timetable == null || !$timetable) {
                            $Error[] = [
                                "Day" => $Day,
                                "Time" => $startTimeFormatted . '' . $endTimeFormatted,
                                "Raw Data" => $value
                            ];
                        }
                    } else {
                        continue;
                    }
                }
            }
            foreach ($Tuesday as $TuesdayRows) {
                $Time = $TuesdayRows['A'];
                $Day = 'Tuesday';
                list($startTime, $endTime) = explode(' - ', $Time);
                $startTimeFormatted = convertTo24HourFormat($startTime);
                $endTimeFormatted = convertTo24HourFormat($endTime);

                $dayslot = dayslot::firstOrCreate(
                    [
                        'day' => $Day,
                        'start_time' => $startTimeFormatted,
                        'end_time' => $endTimeFormatted,
                    ]
                );
                $dayslotId = $dayslot->id;

                foreach ($TuesdayRows as $index => $value) {
                    if ($value == $Time) {
                        continue;
                    } else if ($value != null && $value != '') {
                        $timetable = Action::insertOrCreateTimetable($value, $dayslotId);
                        if ($timetable) {
                            if ($timetable['status'] == 'error'){
                                $Error[] = $timetable;
                            }else{
                                $Success[] = $timetable;
                            }
                        } else if ($timetable == null || !$timetable) {
                            $Error[] = [
                                "Day" => $Day,
                                "Time" => $startTimeFormatted . '' . $endTimeFormatted,
                                "Raw Data" => $value
                            ];
                        }
                    } else {
                        continue;
                    }
                }
            }
            foreach ($Wednesday as $WedRows) {
                $Time = $WedRows['A'];
                $Day = 'Wednesday';
                list($startTime, $endTime) = explode(' - ', $Time);
                $startTimeFormatted = convertTo24HourFormat($startTime);
                $endTimeFormatted = convertTo24HourFormat($endTime);

                $dayslot = dayslot::firstOrCreate(
                    [
                        'day' => $Day,
                        'start_time' => $startTimeFormatted,
                        'end_time' => $endTimeFormatted,
                    ]
                );
                $dayslotId = $dayslot->id;

                foreach ($WedRows as $index => $value) {
                    if ($value == $Time) {
                        continue;
                    } else if ($value != null && $value != '') {
                        $timetable = Action::insertOrCreateTimetable($value, $dayslotId);
                        if ($timetable) {
                            if ($timetable['status'] == 'error') {
                                $Error[] = $timetable;
                            }else{
                                $Success[] = $timetable;
                            }
                            
                        } else if ($timetable == null || !$timetable) {
                            $Error[] = [
                                "Day" => $Day,
                                "Time" => $startTimeFormatted . '' . $endTimeFormatted,
                                "Raw Data" => $value
                            ];
                        }
                    } else {
                        continue;
                    }
                }
            }
            foreach ($Thursday as $ThuRows) {
                $Time = $ThuRows['A'];
                $Day = 'Thursday';
                list($startTime, $endTime) = explode(' - ', $Time);
                $startTimeFormatted = convertTo24HourFormat($startTime);
                $endTimeFormatted = convertTo24HourFormat($endTime);

                $dayslot = dayslot::firstOrCreate(
                    [
                        'day' => $Day,
                        'start_time' => $startTimeFormatted,
                        'end_time' => $endTimeFormatted,
                    ]
                );
                $dayslotId = $dayslot->id;

                foreach ($ThuRows as $index => $value) {
                    if ($value == $Time) {
                        continue;
                    } else if ($value != null && $value != '') {
                        $timetable = Action::insertOrCreateTimetable($value, $dayslotId);
                        if ($timetable) {
                            if ($timetable['status'] == 'error'){
                                $Error[] = $timetable;
                            }else{
                                $Success[] = $timetable;
                            }
                        } else if ($timetable == null || !$timetable) {
                            $Error[] = [
                                "Day" => $Day,
                                "Time" => $startTimeFormatted . '' . $endTimeFormatted,
                                "Raw Data" => $value
                            ];
                        }
                    } else {
                        continue;
                    }
                }
            }

            foreach ($Firday as $FriRows) {
                $Time = $FriRows['A'];
                $Day = 'Friday';
                list($startTime, $endTime) = explode(' - ', $Time);
                $startTimeFormatted = convertTo24HourFormat($startTime);
                $endTimeFormatted = convertTo24HourFormat($endTime);

                $dayslot = dayslot::firstOrCreate(
                    [
                        'day' => $Day,
                        'start_time' => $startTimeFormatted,
                        'end_time' => $endTimeFormatted,
                    ]
                );
                $dayslotId = $dayslot->id;

                foreach ($FriRows as $index => $value) {
                    if ($value == $Time) {
                        continue;
                    } else if ($value != null && $value != '') {
                        $timetable = Action::insertOrCreateTimetable($value, $dayslotId);
                        if ($timetable) {
                            if ($timetable['status'] == 'error'){
                                $Error[] = $timetable;
                            }else{
                                $Success[] = $timetable;
                            }
                        } else if ($timetable == null || !$timetable) {
                            $Error[] = [
                                "Day" => $Day,
                                "Time" => $startTimeFormatted . '' . $endTimeFormatted,
                                "Raw Data" => $value
                            ];
                        }
                    } else {
                        continue;
                    }
                }
            }
            $successCount = count($Success);
            $errorCount = count($Error);
            return response()->json(
                [
                    'Message' => 'Data Inserted Successfully !',
                    'data' => [
                        "Successfully Added Records Count :"=>$successCount,
                        "Faulty Records Count :"=>$errorCount,
                        "Sucess" => $Success,
                        "Error" => $Error,
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
