<?php

namespace App\Http\Controllers;
use App\Models\Action;
use App\Models\dayslot;
use App\Models\excluded_days;
use App\Models\FileHandler;
use App\Models\grader;
use App\Models\juniorlecturer;
use App\Models\program;
use App\Models\StudentManagement;
use App\Models\teacher;
use App\Models\teacher_grader;
use App\Models\teacher_juniorlecturer;
use App\Models\venue;
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
class DatacellModuleController extends Controller
{
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
            $CountRow = 2;
            foreach ($nonEmpty as $index => $row) {
                $section_id = (new section())->getIDByName($row['A']);
                if (!$section_id) {
                    $section_id = section::addNewSection($row['A']);
                }
                $RawData = " Section = " . $row['A'] . " , Course = " . $row['B'] . " , Teacher = " . $row['D'];
                if (!$section_id) {
                    $FaultyData[] = ["status" => "error", "Issue" => "Row No {$CountRow} : Section Format is Not Supported {$RawData}"];
                    $CountRow++;
                    continue;
                }
                $course_id = (new course())->getIDByName($row['B']);
                if (!$course_id) {
                    $FaultyData[] = ["status" => "error", "Issue" => "Row No {$CountRow} : Course Not Found {$RawData}"];
                    $CountRow++;
                    continue;
                }
                $teacher_id = (new teacher())->getIDByName($row['D']);
                if (!$teacher_id) {
                    $FaultyData[] = ["status" => "error", "Issue" => "Row No {$CountRow} : Teacher Not Found {$RawData}"];
                    $CountRow++;
                    continue;
                }
                $offered_course = offered_courses::firstOrCreate(
                    [
                        "course_id" => $course_id,
                        "session_id" => $session_id
                    ]
                );
                $offered_course_id = $offered_course->id;
                $teacherOfferedCourse = teacher_offered_courses::updateOrCreate(
                    [
                        'offered_course_id' => $offered_course_id,
                        'section_id' => $section_id,
                    ],
                    [
                        'teacher_id' => $teacher_id,
                    ]
                );
                $Succesfull[] = ["status" => "success", "data" => "Row No {$CountRow}" . $RawData];
                $CountRow++;
            }
            return response()->json(
                [
                    'message' => 'The Teacher Enrollments Are Added',
                    'data' => [
                        "Total Data " => count($nonEmpty),
                        "Total Inserted " => count($Succesfull),
                        "Total Failure " => count($FaultyData),
                        "FaultyData" => $FaultyData,
                        "Sucessfull" => $Succesfull
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
    public function ExcludedDays(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $expectedFormat = ['A' => 'Reason', 'B' => 'Type', 'C' => 'Date'];
            $headerRow = $sheetData[1] ?? [];
            $headerRow['A'] = trim($headerRow['A']);
            $headerRow['B'] = trim($headerRow['B']);
            $headerRow['C'] = trim($headerRow['C']);
            if (array_diff_assoc($expectedFormat, $headerRow)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid file format. Expected header: ' . implode(', ', $expectedFormat),
                ], 422);
            }
            $filteredData = array_slice($sheetData, 1);
            $nonEmpty = [];
            foreach ($filteredData as $row) {
                if (
                    array_filter($row, function ($value) {
                        return !is_null($value);
                    })
                ) {
                    $row = array_map('trim', $row);
                    $nonEmpty[] = $row;
                }
            }
            $FaultyData = [];
            $Succesfull = [];
            $CountRow = 2;
            foreach ($nonEmpty as $row) {
                $RawData = "Reason = " . $row['A'] . ", Type = " . $row['B'] . ", Date = " . $row['C'];
                $reason = trim($row['A']);
                $type = trim($row['B']);
                $date = trim($row['C']);

                if (empty($reason) || empty($type) || empty($date)) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Missing required fields. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                $validTypes = ['Holiday', 'Reschedule', 'Exam'];
                if (!in_array($type, $validTypes)) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Invalid type '{$type}'. Valid types are: " . implode(', ', $validTypes) . ". {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                if (!Carbon::hasFormat($date, 'Y-m-d')) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Invalid date format '{$date}'. Expected format: YYYY-MM-DD. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                $excludedDay = excluded_days::where('date', $date)->first();
                if ($excludedDay) {
                    $excludedDay->update([
                        'type' => $type,
                        'reason' => $reason,
                    ]);
                    $Succesfull[] = [
                        "status" => "success",
                        "data" => "Row No {$CountRow}: Updated existing record. {$RawData}"
                    ];
                } else {
                    excluded_days::create([
                        'date' => $date,
                        'type' => $type,
                        'reason' => $reason,
                    ]);
                    $Succesfull[] = [
                        "status" => "success",
                        "data" => "Row No {$CountRow}: Inserted new record. {$RawData}"
                    ];
                }
                $CountRow++;
            }
            return response()->json([
                'message' => 'The excluded days have been processed successfully.',
                'data' => [
                    "Total Data" => count($nonEmpty),
                    "Total Inserted/Updated" => count($Succesfull),
                    "Total Errors" => count($FaultyData),
                    "FaultyData" => $FaultyData,
                    "Succesfull" => $Succesfull
                ]
            ], 200);

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
    public function processSessionRecords(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $expectedFormat = ['A' => 'name', 'B' => 'year', 'C' => 'start_date', 'D' => 'end_date'];
            $headerRow = $sheetData[1] ?? [];
            $headerRow = array_map('trim', $headerRow);
            if (array_diff_assoc($expectedFormat, $headerRow)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid file format. Expected header: ' . implode(', ', $expectedFormat),
                ], 422);
            }
            $filteredData = array_slice($sheetData, 1);
            $nonEmpty = array_filter($filteredData, function ($row) {
                return !empty(array_filter($row, function ($value) {
                    return trim($value) !== '';
                }));
            });

            $FaultyData = [];
            $Succesfull = [];
            $CountRow = 2;

            foreach ($nonEmpty as $row) {
                $name = trim($row['A']);
                $year = trim($row['B']);
                $startDate = trim($row['C']);
                $endDate = trim($row['D']);
                $RawData = "Name = {$name}, Year = {$year}, Start Date = {$startDate}, End Date = {$endDate}";
                if (empty($name) || empty($year) || empty($startDate) || empty($endDate)) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Missing required fields. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                try {
                    $startDateObj = Carbon::createFromFormat('Y-m-d', $startDate);
                    $endDateObj = Carbon::createFromFormat('Y-m-d', $endDate);
                } catch (Exception $e) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Invalid date format. Expected format: YYYY-MM-DD. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                if ($startDateObj->greaterThanOrEqualTo($endDateObj)) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: End date must be greater than start date. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                $overlap = session::where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($query) use ($startDate, $endDate) {
                            $query->where('start_date', '<=', $startDate)
                                ->where('end_date', '>=', $endDate);
                        });
                })->exists();

                if ($overlap) {
                    $FaultyData[] = [
                        "status" => "error",
                        "Issue" => "Row No {$CountRow}: Date range overlaps with an existing session. {$RawData}"
                    ];
                    $CountRow++;
                    continue;
                }
                $existingSession = session::where('name', $name)
                    ->where('year', $year)
                    ->first();

                if ($existingSession) {
                    $existingSession->update([
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]);
                    $Succesfull[] = [
                        "status" => "success",
                        "data" => "Row No {$CountRow}: Updated existing session. {$RawData}"
                    ];
                } else {
                    session::create([
                        'name' => $name,
                        'year' => $year,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]);
                    $Succesfull[] = [
                        "status" => "success",
                        "data" => "Row No {$CountRow}: Inserted new session. {$RawData}"
                    ];
                }

                $CountRow++;
            }

            return response()->json([
                'message' => 'The sessions have been processed successfully.',
                'data' => [
                    "Total Data" => count($nonEmpty),
                    "Total Inserted/Updated" => count($Succesfull),
                    "Total Errors" => count($FaultyData),
                    "FaultyData" => $FaultyData,
                    "Succesfull" => $Succesfull
                ]
            ], 200);

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
    public function importVenues(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $expectedHeader = 'venue';
            $headerRow = $sheetData[1]['A'] ?? null;
            if (trim(strtolower($headerRow)) !== $expectedHeader) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Invalid file format. Expected header: '{$expectedHeader}' in column A.",
                ], 422);
            }
            $filteredData = array_slice($sheetData, 1);
            $nonEmptyRows = array_filter($filteredData, function ($row) {
                return !empty($row['A']);
            });

            $faultyData = [];
            $successful = [];
            $rowCount = 2; // Start from the second row since the first row is the header

            foreach ($nonEmptyRows as $row) {
                $rawVenue = trim($row['A']);

                if (empty($rawVenue)) {
                    $faultyData[] = [
                        'status' => 'error',
                        'Issue' => "Row No {$rowCount}: Venue name is empty."
                    ];
                    $rowCount++;
                    continue;
                }

                try {
                    $venue = venue::firstOrCreate(['venue' => $rawVenue]);

                    $successful[] = [
                        'status' => 'success',
                        'data' => "Row No {$rowCount}: Venue '{$venue->venue}' was added or already exists."
                    ];
                } catch (Exception $e) {
                    $faultyData[] = [
                        'status' => 'error',
                        'Issue' => "Row No {$rowCount}: Unable to process venue '{$rawVenue}'. Error: " . $e->getMessage()
                    ];
                }

                $rowCount++;
            }

            return response()->json([
                'message' => 'The venues have been processed successfully.',
                'data' => [
                    "Total Data" => count($nonEmptyRows),
                    "Total Processed" => count($successful),
                    "Total Errors" => count($faultyData),
                    "FaultyData" => $faultyData,
                    "Successful" => $successful
                ]
            ], 200);

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
    public function importSections(Request $request)
    {
        try {
            $request->validate([
                'excel_file' => 'required|mimes:xlsx,xls',
            ]);
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load(filename: $file->getPathname());
            $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $expectedHeader = ['A' => 'Section'];
            $headerRow = $sheetData[1] ?? [];
            $headerRow = array_map('trim', $headerRow); // Trim header values
            if (array_diff_assoc($expectedHeader, $headerRow)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid file format. Expected header: ' . implode(', ', $expectedHeader),
                ], 422);
            }
            $dataRows = array_slice($sheetData, 1);
            $nonEmptyRows = array_filter($dataRows, function ($row) {
                return !empty(trim($row['A'] ?? ''));
            });

            $successful = [];
            $faultyData = [];
            $rowNumber = 2;

            foreach ($nonEmptyRows as $row) {
                $sectionName = trim($row['A'] ?? '');
                if (empty($sectionName)) {
                    $faultyData[] = [
                        'status' => 'error',
                        'Issue' => "Row No {$rowNumber}: Missing section name.",
                    ];
                    $rowNumber++;
                    continue;
                }
                try {
                    $sectionId = section::addNewSection($sectionName);
                    if ($sectionId) {
                        $successful[] = [
                            'status' => 'success',
                            'data' => "Row No {$rowNumber}: Section '{$sectionName}' added or found with ID {$sectionId}.",
                        ];
                    } else {
                        $faultyData[] = [
                            'status' => 'error',
                            'Issue' => "Row No {$rowNumber}: Invalid section format '{$sectionName}'.",
                        ];
                    }
                } catch (Exception $e) {
                    $faultyData[] = [
                        'status' => 'error',
                        'Issue' => "Row No {$rowNumber}: Failed to process section '{$sectionName}'. Error: " . $e->getMessage(),
                    ];
                }
                $rowNumber++;
            }
            return response()->json([
                'message' => 'Sections processed successfully.',
                'data' => [
                    'Total Rows' => count($nonEmptyRows),
                    'Total Successful' => count($successful),
                    'Total Errors' => count($faultyData),
                    'Successful' => $successful,
                    'Faulty Data' => $faultyData,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred.',
                'error' => $e->getMessage(),
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
                        "Total Student Rows" => count($NonEmptyRow),
                        "Added Enrollments" => count($successMessages),
                        "Failed Enrollments" => count($errorMessages),
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
    public function AddOrUpdateTeacher(Request $request)
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
                $filteredData[] = array_slice($row, 0, 4);
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

            $successMessages = [];
            $errorMessages = [];
            foreach ($nonEmpty as $singleRow) {
                $RowNo++;
                $name = $singleRow['A'];
                $dateOfBirth = $singleRow['B'];
                $gender = $singleRow['C'];
                $email = $singleRow['D'];
                $username = strtolower(str_replace(' ', '', $name)) . '@biit.edu';
                $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
                $password = Action::generateUniquePassword($name);
                $userId = Action::addOrUpdateUser($username, $password, $email, 'Teacher');
                if (!$userId) {
                    $errorMessages[] = ["status" => 'failed', "reason" => "Failed to create or update user for {$name}."];
                    continue;
                }
                $teacher = Teacher::where('name', $name)->first();
                if ($teacher) {
                    $teacher->update([
                        'user_id' => $userId,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $successMessages[] = ["status" => 'success', "Logs" => "The teacher with Name: {$name} was updated."];
                } else {
                    Teacher::create([
                        'user_id' => $userId,
                        'name' => $name,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $successMessages[] = ["status" => 'success', "Logs" => "The teacher with Name: {$name} was added."];
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
            $successMessages = [];
            $errorMessages = [];
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
                    $errorMessages[] = ["status" => 'failed', "reason" => "The Field Current Section {$currentSection} Format is Not Correct !"];
                }

                $session_id = (new session())->getSessionIdByName($InTake);

                if ($session_id == 0) {
                    $errorMessages[] = ["status" => 'failed', "reason" => "The Field Current Session  {$InTake} Format is Not Correct !"];
                }
                $program_id = program::where('name', $Discipline)->value('id');
                if (!$program_id) {
                    $errorMessages[] = ["status" => 'failed', "reason" => "The Field Disciplein  {$Discipline} Format is Not Correct !"];
                }

                $student = StudentManagement::addOrUpdateStudent($regNo, $Name, $cgpa, $gender, $dob, $guardian, null, $user_id, $section_id, $program_id, $session_id, $status);

                if ($student) {
                    $successMessages[] = ["status" => 'success', "Logs" => "The Student with RegNo : {$regNo} ,Name : {$Name}  is added to Record !", "Username" => $regNo, "Password" => $password];

                } else {
                    $errorMessages[] = ["status" => 'failed', 'Reason' => 'Unknown'];
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
                $filteredData[] = array_slice($row, 0, 8);
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
    public function AddOrUpdateJuniorLecturers(Request $request)
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
                $username = strtolower(str_replace(' ', '', $name)) . '_jl@biit.edu'; // Custom username format for JuniorLecturer
                $userExists = User::where('username', $username)->exists();
                if ($userExists) {
                    $status[] = ["status" => 'failed', "reason" => "Username {$username} already exists!"];
                    continue;
                }
                $formattedDOB = (new DateTime($dateOfBirth))->format('Y-m-d');
                $password = Action::generateUniquePassword($name);
                $userId = Action::addOrUpdateUser($username, $password, $email, 'JuniorLecturer'); // Set User type as JuniorLecturer

                if (!$userId) {
                    $status[] = ["status" => 'failed', "reason" => "Failed to create or update user for {$name}."];
                    continue;
                }
                $juniorLecturer = JuniorLecturer::where('name', $name)->first();

                if ($juniorLecturer) {
                    $juniorLecturer->update([
                        'user_id' => $userId,
                        'name' => $name,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The JuniorLecturer with Name: {$name} was updated."];
                } else {
                    JuniorLecturer::create([
                        'user_id' => $userId,
                        'name' => $name,
                        'date_of_birth' => $formattedDOB,
                        'gender' => $gender
                    ]);
                    $status[] = ["status" => 'success', "Logs" => "The JuniorLecturer with Name: {$name} was added."];
                }
            }

            // Categorize success and failed messages
            $successMessages = [];
            $errorMessages = [];
            foreach ($status as $stat) {
                if ($stat['status'] === 'success') {
                    $successMessages[] = $stat;
                } else {
                    $errorMessages[] = $stat;
                }
            }

            // Return the response
            return response()->json(
                [
                    'Message' => 'JuniorLecturers Processed Successfully!',
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
                $checkGrader=teacher_grader::where('grader_id',$grader->id)
               ->where('session_id',$sessionId)->get();
                if(count($checkGrader)>0){
                    $status[] = ["status" => 'error', "message" => "The Grader is Already Assigned to A differet teacher in given Session Cannot Assign 1 Grader to Multiple Teacher :  RegNo {$regNo}.","teacher" => $name, "session" => $sessionName];
                    continue;
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
                        'feedback' => ''
                    ]);
                    $status[] = ["status" => 'success', "message" => "Grader assigned :  RegNo {$regNo}.","teacher" => $name, "session" => $sessionName];
                }else{
                    $status[] = ["status" => 'success', "message" => "Grader is Already Assigned to The Teacher : RegNo {$regNo}.", "teacher" => $name, "session" => $sessionName];
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
    
}
