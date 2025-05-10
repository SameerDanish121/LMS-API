<?php
use App\Models\Hod;
use App\Models\Course;
use App\Models\program;
use App\Models\section;
use App\Models\session;
use App\Models\student;
use App\Models\teacher;
use App\Models\Director;
use App\Models\juniorlecturer;
use App\Models\offered_courses;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\GraderController;
use App\Http\Controllers\StudentsController;
use App\Http\Controllers\TeachersController;
use App\Http\Controllers\DatacellsController;
use App\Http\Controllers\JuniorLecController;
use App\Http\Controllers\ExtraKhattaController;
use App\Http\Controllers\CourseContentContoller;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\DatacellModuleController;
use App\Http\Controllers\HodController;
use App\Http\Controllers\SingleInsertionController;




//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~Testing~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
Route::get('/', function () {
    return response()->json(['status' => 'success'], 200);
});
Route::post('/test_fcm', [NotificationController::class, 'TestFirebase']);
Route::get('/current/session',function(){
    return (new session())->getCurrentSessionWeek()??0;
});
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~Authentication~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
Route::get('/Login', [StudentsController::class, 'Login']);
Route::post('/forgot-password', [AuthenticationController::class, 'sendOTP']);
Route::post('/verify-otp', [AuthenticationController::class, 'verifyOTP']);
Route::post('/update-pass', [AuthenticationController::class, 'updatePassword']);
Route::post('/verify/login', [AuthenticationController::class, 'verifyLoginOTP']);
Route::post('/store-fcmtoken', [NotificationController::class, 'storeFcmToken']);
Route::get('/remember', [StudentsController::class, 'RememberMe']);
Route::post('/student/notification', [NotificationController::class, 'SendNotificationToStudent']);

Route::prefix('Uploading')->group(function () { 
    Route::post('/uplaod/Result', [DatacellModuleController::class, 'UploadExamAwardList']);
    Route::post('/uplaod/Topic', [DatacellModuleController::class, 'UploadCourseContentTopic']);
    Route::post('/excel-upload/assign-juniorlecturer', [DatacellModuleController::class, 'assignJuniorLecturer']);
    Route::post('/excel-uploading/graders-assign', [DatacellModuleController::class, 'assignGrader']);
    Route::post('/excel-upload/student-enrollments', [DatacellModuleController::class, 'uploadStudentEnrollments']);
    Route::post('/excel-upload/offeredcourse_teacherallocation', [DatacellModuleController::class, 'OfferedCourseTeacheruploadExcel']);
    Route::post('/excel-upload/add-or-update-courses', [DatacellModuleController::class, 'AddOrUpdateCourses']);
    Route::post('/excel-upload/excluded_days', [DatacellModuleController::class, 'ExcludedDays']);
    Route::post('/excel-upload/session', [DatacellModuleController::class, 'processSessionRecords']);
    Route::post('/excel-upload/venues', [DatacellModuleController::class, 'importVenues']);
    Route::post('/excel-upload/sections', [DatacellModuleController::class, 'importSections']);
    Route::post('/excel-uplaod/add-or-update-student', [DatacellModuleController::class, 'addOrUpdateStudent']);
    Route::post('/excel-upload/add-or-update-teacher', [DatacellModuleController::class, 'addOrUpdateTeacher']);
    Route::post('/excel-upload/upload-junior-lecturers', [DatacellModuleController::class, 'AddOrUpdateJuniorLecturers']);
    Route::post('/uplaod/timetable', [DatacellModuleController::class, 'UploadTimetableExcel']);
    Route::post('/timetable/section', [DatacellModuleController::class, 'getTimetableGroupedBySection']);
    Route::post('/uplaod/SubjectFullResult', [ExtraKhattaController::class, 'AddSubjectResult']);
    Route::post('/uplaod/Exam', [DatacellModuleController::class, 'CreateExam']);
});
Route::prefix('Insertion')->group(function () {
    Route::post('/add-single-teacher', [SingleInsertionController::class, 'AddSingleTeacher']);
    Route::post('/add-single-junior', [SingleInsertionController::class, 'AddSingleJuniorLecturer']);
    Route::post('/add-admin', [SingleInsertionController::class, 'AddSingleAdmin']);
    Route::post('/add-datacell', [SingleInsertionController::class, 'AddSingleDatacell']);
    Route::post('/send-notification', [SingleInsertionController::class, 'pushNotification']);
    Route::post('/update-single-user', [SingleInsertionController::class, 'UpdateSingleUser']);
    Route::post('/add-single-student', [SingleInsertionController::class, 'addStudent']);
    Route::post('/add-session', [SingleInsertionController::class, 'addSession']);
    Route::post('/update-session/{id}', [SingleInsertionController::class, 'updateSession']);
}); 
Route::prefix('Dropdown')->group(function () {
    Route::get('/AllStudent', function () {
        return student::all('name')->pluck('name');
    });
    Route::get('/AllTeacher', function () {
        return teacher::all('name')->pluck('name');
    });
    Route::get('/AllJL', function () {
        return juniorlecturer::all('name')->pluck('name');
    });
    Route::get('/AllCourse', function () {
        return Course::all('name')->pluck('name');
    });
    Route::get('/AllSections', function () {
        $sections = section::all();
        $formattedSections = $sections->map(function ($section) {
            $program = in_array($section->program, ['BCS', 'BAI', 'BSE', 'BIT'])
                ? $section->program . '-'
                : $section->program;

            return $program . $section->semester . $section->group;
        });

        return response()->json($formattedSections);
    });
    Route::get('/AllSessions', function () {
        $sessions = Session::orderBy('start_date', 'desc')->get();

        $formattedSessions = $sessions->map(function ($session) {
            return (new Session())->getSessionNameByID($session->id);
        });
        return response()->json($formattedSessions);
    });
    Route::get('/AllOfferedCourse', function () {
        $offeredCourses = offered_courses::with(['course', 'session'])
            ->get()
            ->sortByDesc(fn($course) => $course->session->start_date ?? '0000-00-00') // Handle missing session dates
            ->map(function ($course) {
                return [
                    'id' => $course->id,
                    'course' => $course->course->name,
                    'session' => (new session())->getSessionNameByID($course->session->id),
                    'data' => $course->course->name . ' (' . (new Session())->getSessionNameByID($course->session->id) . ')'
                ];
            })
            ->values();
        return response()->json($offeredCourses, 200);
    });
    Route::get('/Director', function () {
        return Director::all();
    });
    Route::get('/HOD', function () {
        return Hod::all();
    });
    Route::get('/AllProgram', function () {
        $programs = program::all()->map(function ($program) {
            return [
                'id' => $program->id,
                'data' => $program->description . ' (' . $program->name . ')'
            ];
        })->values();

        return response()->json($programs, 200);
    });
    Route::get('/AllSession', function () {
        $programs = session::all()->map(function ($session) {
            return [
                'id' => $session->id,
                'data' => $session->name . '-' . $session->year,
                'name' => $session->name . '-' . $session->year
            ];
        })->values();
        return response()->json($programs, 200);
    });
    Route::get('/AllSection', function () {
        $sections = section::all()->map(function ($section) {
            $program = in_array($section->program, ['BCS', 'BAI', 'BSE', 'BIT'])
                ? $section->program . '-'
                : $section->program;
            return [
                'id' => $section->id,
                'data' => $program . $section->semester . $section->group
            ];
        })->values();

        return response()->json($sections, 200);
    });
    Route::get('/AllStudentData', function () {
        $sections = student::all()->map(function ($student) {
            return [
                'id' => $student->id,
                'name' => $student->name,
                'regno'=>$student->RegNo,
                'Format'=>$student->name.' ('.$student->RegNo.')'
            ];
        })->values();
        return response()->json($sections, 200);
    });
    Route::get('/get-teachers', function () {
        $sections = teacher::all()->map(function ($section) {
            return [
                'id' => $section->id,
                'name' => $section->name
            ];
        })->values();
        return response()->json($sections, 200);
    });
    Route::get('get-students', function () {
        $sections = student::all()->map(function ($section) {
            return [
                'id' => $section->id,
                'name' => $section->name
            ];
        })->values();
        return response()->json($sections, 200);
    });
});
Route::prefix('Archives')->group(function () {
    Route::get('/Directory', [StudentsController::class, 'getBIITFolderInfo']);
    Route::get('/getArchivesDetails', [DatacellModuleController::class, 'Archives']);
    Route::delete('/clean-transcript', [StudentsController::class, 'cleanTranscriptFolder']);
    Route::post('/compress-folder', [StudentsController::class, 'compressFolder']);
    Route::delete('/DeleteFolderByPath', [DatacellModuleController::class, 'DeleteFolderByPath']);
});

Route::prefix('Admin')->group(function () {
    Route::get('/AllStudent', [AdminController::class, 'AllStudent']);
    Route::get('/sections', [AdminController::class, 'showSections']);
    Route::get('/teachers', [AdminController::class, 'AllTeacher']);
    Route::get('/courses', [AdminController::class, 'AllCourse']);
    Route::get('/junior-lectures', [AdminController::class, 'allJuniorLecturers']);
    Route::get('/grades', [AdminController::class, 'AllGrades']);
    Route::get('/sessions', [AdminController::class, 'getAllSessions']);
    Route::get('/viewTranscript', [StudentsController::class, 'ViewTranscript']);
    Route::get('/getCourseContent', [AdminController::class, 'getCourseContentWithTopics']);
    Route::get('/getCourseAllocation', [AdminController::class, 'getAllAllocatedCourses']);
    Route::get('/un-assigned/teacher-for-grader', [AdminController::class, 'unassignedTeacherWithGrader']);



    Route::post('/update-add-program', [AdminController::class, 'addOrUpdateProgram']);
    Route::get('/teacher-graders', [AdminController::class, 'getAllTeacherGraders']);
    Route::get('/course-content', [AdminController::class, 'getCourseContent']);
    Route::get('/search-admin', [AdminController::class, 'searchAdminByName']);
    Route::get('/search-datacell', [AdminController::class, 'GetDatacell']);
    Route::get('/Current-Courses/{sessionId}', [AdminController::class, 'getCoursesInCurrentSession']);
    Route::get('/Courses-Not-In-Session/{sessionId}', [AdminController::class, 'getCoursesNotInSession']);
    Route::get('/Teachers-With-No-Courses/{sessionId}', [AdminController::class, 'getTeachersWithNoCourses']);
    Route::get('/Teacher-Courses/{teacherId}/{sessionId}', [AdminController::class, 'getTeacherEnrolledCourses']);
    Route::get('/Students-Not-Enrolled-Courses/{sessionId}', [AdminController::class, 'getStudentsNotEnrolledInSession']);
    Route::get('/Student-Courses/{studentName}/{sessionId}', [AdminController::class, 'getStudentCoursesInSession']);
    Route::get('/failed-courses', [AdminController::class, 'getFailedStudents']);
    Route::get('/failed-students', [AdminController::class, 'getFailedStudentsInOfferedCourse']);
    Route::get('/TeacherJLec', [AdminController::class, 'getTeacherJuniorLecturers']);
    Route::get('/assigned-graders', [AdminController::class, 'getTeachersWithAssignedGraders']);
    Route::get('/TeacherwithNoGrader', [AdminController::class, 'getTeachersWithoutGraders']);
    Route::get('/unassigned-graders', [AdminController::class, 'getUnassignedGraders']);
    Route::get('/Teacherfreeslots', [AdminController::class, 'noClassesToday']);
    Route::get('/TeacherJLecList', [AdminController::class, 'getAllTeachersWithJuniorLecturers']);
    Route::get('/history', [AdminController::class, 'getGraderHistory']);
});
Route::prefix('Datacells')->group(function () {
    Route::get('/temporary-enrollments', [TeachersController::class, 'getTemporaryEnrollmentsRequest']);
    Route::post('/process-temporary-enrollments', [TeachersController::class, 'ProcessTemporaryEnrollments']);
    Route::post('/assign-grader', [ExtraKhattaController::class, 'assignGrader']);
    Route::post('/add-grader', [ExtraKhattaController::class, 'AddGrader']);


    Route::post('/enroll-student', [ExtraKhattaController::class, 'addStudentEnrollment']);
    Route::post('/NewOfferedCourse', [DatacellsController::class, 'AddNewOfferedCourse']);
    Route::post('/offered-courses/add-or-check', [ExtraKhattaController::class, 'addOrCheckOfferedCourses']);
    Route::post('/teacher-offered-courses/add-multiple', [ExtraKhattaController::class, 'addTeacherOfferedCourses']);
    Route::post('/teacher-offered-courses/update-or-insert-multiple', [ExtraKhattaController::class, 'updateOrInsertTeacherOfferedCourses']);
    Route::post('/assign-junior-lecturer', [ExtraKhattaController::class, 'assignJuniorLecturer']);
    Route::post('/update-or-create-junior-lecturer', [ExtraKhattaController::class, 'updateOrCreateTeacherJuniorLecturer']);
});



Route::prefix('Teachers')->group(function () {
    Route::post('/add-or-update-feedbacks', [TeachersController::class, 'AddFeedback']);
    Route::get('/contest-list', [TeachersController::class, 'ContestList']);
    Route::post('/process-contest', [TeachersController::class, 'ProcessContest']);
    Route::get('/FullTimetable', [TeachersController::class, 'FullTimetable']);
    Route::get('/your-courses', [TeachersController::class, 'YourCourses']);
    Route::get('/get/notifications', [TeachersController::class, 'getTeacherNotifications']);
    Route::get('/today',[TeachersController::class, 'TodayClass']);
    Route::post('/re-take', [TeachersController::class, 'ReTakeAttendanceWithStatus']);
    Route::post('/attendance/mark-bulk', [JuniorLecController::class, 'markBulkAttendance']);
    Route::post('/attendance-list', [TeachersController::class, 'SortedAttendanceList']);
    Route::get('/venues', [TeachersController::class, 'getAllVenues']);
    Route::get('classestoday/{teacher_id}', [TeachersController::class, 'getTodayClassesWithAttendanceStatus']);
    Route::get('/All-courses/{teacher_id}', [TeachersController::class, 'getAllOfferedCourses']);
    Route::post('/update-teacher-password', [TeachersController::class, 'updateTeacherPassword']);
    Route::post('/update-teacher-image', [TeachersController::class, 'updateTeacherImage']);
    Route::post('/update-teacher-email', [TeachersController::class, 'updateTeacherEmail']);
    Route::post('/copy/previousSemesterCourseContent', [TeachersController::class, 'CopySemester']);
    Route::post('/update/course-content-topic-status', [TeachersController::class, 'updateCourseContentTopicStatus']);
    Route::get('/sections/info', [TeachersController::class, 'getSectionList']);
    Route::get('/section-task-result', [TeachersController::class, 'getSectionTaskResult']);
    Route::get('/get-single-student-task-result', [TeachersController::class, 'getSingleStudentTaskResult']);
    Route::get('/section-attendance-list', [TeachersController::class, 'getAttendanceBySubjectForAllStudents']);
    Route::get('/task/get', [TeachersController::class, 'YourTaskInfo']);
    Route::get('/tasks/unassigned-to-grader', [TeachersController::class, 'UnAssignedTaskToGrader']);
   
   
    Route::get('/teacher-unassigned-task', [TeachersController::class, 'getListofUnassignedTask']);
    Route::post('/store-task', [TeachersController::class, 'storeTask']);
    Route::post('/consider-task', [TeachersController::class, 'storeOrUpdateTaskConsiderations']);
    Route::post('/temporary-enrollment', [TeachersController::class, 'AddRequestForTemporaryEnrollment']);
    Route::post('/attendance/mark-single', [JuniorLecController::class, 'markSingleAttendance']);
    Route::get('/active-junior-lecturers', [TeachersController::class, 'getYourActiveJuniorLecturer']);
    Route::post('/submit-number-list', [TeachersController::class, 'SubmitNumberList']);
    Route::get('/get-task-submissions', [TeachersController::class, 'getTaskSubmissionList']);
    Route::post('/add-sequence-attendance', [TeachersController::class, 'addAttendanceSeatingPlan']);
   
    Route::get('/get-exam-result', [TeachersController::class, 'getSectionExamList']);
    Route::post('/uplaod/course-content', [DatacellModuleController::class, 'CreateCourseContent']);
    Route::get('/get_course_content', [CourseContentContoller::class, 'getTeacherCourseContent']);
    Route::get('/topic', [CourseContentContoller::class, 'GetTopicsDetails']);
    Route::post('/update-course-content', [CourseContentContoller::class, 'UpdateTopicStatus']);
    Route::post('/create/course_content', [CourseContentContoller::class, 'AddSingleCourseContent']);
    Route::get('/active/courses', [CourseContentContoller::class, 'YourCurrentSessionCourses']);
    Route::get('/task/un-assigned', [CourseContentContoller::class, 'getTaskDetails']);
    Route::post('/create/task', [CourseContentContoller::class, 'storeTask']);
    Route::get('/teacher-graders', [TeachersController::class, 'getAssignedGraders']);

    Route::delete('/remover/task', [TeachersController::class, 'deleteTask']);
    Route::post('/task/update-enddatetime', [TeachersController::class, 'updateTaskEndDateTime']);
    Route::get('/grader_assign', [TeachersController::class, 'workloadOfGrader']);
    Route::post('/tasks/assign-grader', [TeachersController::class, 'assignTaskToGrader']);
    Route::post('/task/update-details', [TeachersController::class, 'updateTaskDetails']);

    Route::post('/task-consideration', [TeachersController::class, 'storeConsideration']);
    Route::get('/summary', [TeachersController::class, 'getConsiderationSummary']);
});


Route::prefix('Un-usable')->group(function () {
    Route::get('/load-file', [TeachersController::class, 'LoadFile']);
    Route::get('/CanBePromoted', [ExtraKhattaController::class, 'Sample']);
    Route::post('/send-Notification', [StudentsController::class, 'sendNotification']);
    Route::post('/update-datacell-image', [DatacellsController::class, 'updateDataCellImage']);
    Route::post('/add-session', [AdminController::class, 'addSingleSession']);
    Route::post('/update-admin-image', [AdminController::class, 'updateAdminImage']);
    Route::post('/EnrollStudent', [DatacellsController::class, 'NewEnrollment']);
});




//////////////////////////////////////////////////////FINISHED/////////////////////////////////////////////////////
Route::prefix('JuniorLec')->group(function () {
    Route::get('classestoday/{juniorLecturerId}', [JuniorLecController::class, 'juniorTodayClassesWithStatus']);
    Route::get('/your-courses', [JuniorLecController::class, 'YourCourses']);
    Route::get('/notifications', [JuniorLecController::class, 'YourNotification']);
    Route::post('/send-notification', [JuniorLecController::class, 'sendNotification']);
    Route::get('/dropdown/active-courses', [JuniorLecController::class, 'ActiveCourseInfo']);
    Route::get('/get/tasks', [JuniorLecController::class, 'getTaskInfo']);
    Route::get('/task-submissions', [JuniorLecController::class, 'getTaskSubmissionList']);
    Route::post('/submit-number', [JuniorLecController::class, 'SubmitNumber']);
    Route::post('/submit-number-list', [JuniorLecController::class, 'SubmitNumberList']);
    Route::get('/attendance-list-lab', [JuniorLecController::class, 'attendanceListofLab']);
    Route::get('/attendance-list/student', [JuniorLecController::class, 'attendanceListofSingleStudent']);
    Route::post('/tasks/store', [JuniorLecController::class, 'storeTask']);
    Route::get('/lab-attendance-list', [JuniorLecController::class, 'getLabAttendanceList']);
    Route::post('/attendance/mark-single', [JuniorLecController::class, 'markSingleAttendance']);
    Route::post('/attendance/mark-bulk', [JuniorLecController::class, 'markBulkAttendance']);
    Route::get('/sections/info', [TeachersController::class, 'getSectionList']);
    Route::get('/section-task-result', [TeachersController::class, 'getSectionTaskResult']);
    Route::get('/get-single-student-task-result', [TeachersController::class, 'getSingleStudentTaskResult']);
    Route::get('/section-attendance-list', [TeachersController::class, 'getAttendanceBySubjectForAllStudents']);
    Route::post('/process-contest', [JuniorLecController::class, 'ProcessContest']);
    Route::get('/jl-unassigned-task', [JuniorLecController::class, 'getListofUnassignedTask']);
    Route::post('/temporary-enrollment', [JuniorLecController::class, 'AddRequestForTemporaryEnrollment']);
    Route::get('/active-teacher-details', [JuniorLecController::class, 'getYourTeacher']);
    Route::post('/attendance-list', [JuniorLecController::class, 'SortedAttendanceList']);
    Route::post('/add-sequence-attendance', [JuniorLecController::class, 'addAttendanceSeatingPlan']);
    Route::post('/update-junior-lecturer-password', [JuniorLecController::class, 'updateJuniorLecturerPassword']);
    Route::post('/update-junior-lecturer-image', [JuniorLecController::class, 'updateJuniorLecturerImage']);
    Route::get('/get/notifications', [JuniorLecController::class, 'getTeacherNotifications']);
    Route::get('/full-timetable', [JuniorLecController::class, 'FullTimetable']);
    Route::get('/contest-list', [JuniorLecController::class, 'ContestList']);
    Route::post('/update-teacher-password', [JuniorLecController::class, 'updateTeacherPassword']);
    Route::post('/update-teacher-image', [TeachersController::class, 'updateTeacherImage']);
    Route::post('/update-teacher-email', [JuniorLecController::class, 'updateTeacherEmail']);
    Route::get('/today',[JuniorLecController::class, 'TodayClass']);
    Route::get('/task/get', [JuniorLecController::class, 'YourTaskInfo']);
    Route::get('/task/un-assigned', [JuniorLecController::class, 'getTaskDetails']);
    Route::post('/create/task', [JuniorLecController::class, 'storeTask']);
    Route::get('/get_course_content', [JuniorLecController::class, 'getTeacherCourseContent']);
    Route::get('/active/courses', [JuniorLecController::class, 'YourCurrentSessionCourses']);
});
Route::prefix('Students')->group(function () {
    Route::post('/PingNotification', [StudentsController::class, 'PingNotification']);
    Route::get('/Notification', [StudentsController::class, 'Notification']);
    Route::get('/FullTimetable', [StudentsController::class, 'FullTimetable']);
    Route::get('/attendance', [StudentsController::class, 'getAttendance']);
    Route::delete('/contested-attendance/{id}/withdraw', [StudentsController::class, 'withdrawRequest']);
    Route::get('/attendancePerSubject', [StudentsController::class, 'AttendancePerSubject']);
    Route::get('/get/notification', [StudentsController::class, 'Notifications']);
    Route::post('/update-password', [StudentsController::class, 'updatePassword']);
    Route::post('/update-student-image', [StudentsController::class, 'updateStudentImage']);
    Route::get('/task/details', [StudentsController::class, 'getTaskDetails']);
    Route::post('/submit-task-file', [StudentsController::class, 'submitFileAnswer']);
    Route::post('/submit-quiz', [StudentsController::class, 'submitQuizAnswer']);
    Route::post('/contest-attendance', [StudentsController::class, 'ContestAttendance']);
    Route::get('/getAllEnrollments', [StudentsController::class, 'getActiveEnrollments']);
    Route::get('/getPreviousEnrollments', [StudentsController::class, 'getYourPreviousEnrollments']);
    Route::get('/Transcript', [StudentsController::class, 'Transcript']);
    Route::get('/TranscriptPDF', [StudentsController::class, 'getTranscriptPdf']);
    Route::get('/TranscriptSessionDropDown', [StudentsController::class, 'TranscriptSessionDropDown']);
    Route::post('/submitTask', [StudentsController::class, 'submitAnswer']);
    Route::get('/current-enrollments', [StudentsController::class, 'StudentCurrentEnrollmentsName']);
    Route::get('/all-enrollments', [StudentsController::class, 'StudentAllEnrollmentsName']);
    Route::get('/subject/task-result', [StudentsController::class, 'GetSubjectTaskResult']);
    Route::post('/exam-result', [StudentsController::class, 'getStudentExamResult']);
    Route::get('/course-content/week', [StudentsController::class, 'GetFullCourseContentOfSubjectByWeek']);
    Route::get('/course-content', [StudentsController::class, 'GetFullCourseContentOfSubject']);
    Route::get('/getStudentCourseContent',[StudentsController::class,'GetFullCourseContentOfStudentByActivePrevious']);
    Route::get('/subject/task-considered', [StudentsController::class, 'getTaskConsiderations']);
    Route::get('/task/evaluated', [StudentsController::class, 'getAllTaskConsidered']);
    Route::get('/calendar', [StudentsController::class, 'getCelendarData']);

    
    Route::post('/update-teacher-password', [JuniorLecController::class, 'updateTeacherPassword']);
    Route::post('/update-teacher-image', [TeachersController::class, 'updateTeacherImage']);
    Route::post('/update-teacher-email', [JuniorLecController::class, 'updateTeacherEmail']);
});
Route::prefix('Grader')->group(function () {
    Route::get('/GraderInfo', [GraderController::class, 'GraderOf']);
    Route::get('/YourTask', [GraderController::class, 'GraderTask']);
    Route::get('/ListOfStudent', [GraderController::class, 'ListOfStudentForTask']);
    Route::post('/SubmitTaskResult', [GraderController::class, 'SubmitNumber']);
    Route::post('/SubmitTaskResultList', [GraderController::class, 'SubmitNumberList']);
});


Route::prefix('Hod')->group(function () {
Route::get('/content', [HodController::class, 'getAllCourseContents']);
Route::post('/add-course', [HodController::class, 'addCourse']);
Route::post('/add-teacher', [HodController::class, 'AddSingleTeacher']);
Route::post('/add-junior', [HodController::class, 'AddSingleJunior']);
Route::post('/link-topics', [HodController::class, 'linkTopicsToCourseContent']);

Route::post('/course-content/bulk-insert', [HodController::class, 'AddMultipleCourseContents']);
Route::get('/offered-courses/grouped', [HodController::class, 'getGroupedOfferedCoursesBySession']);

});