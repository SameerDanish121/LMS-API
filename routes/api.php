<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\GraderController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\JuniorLecturerController;
use App\Http\Controllers\DatacellController;

////////////////////////////////////////////////////////~api/~////////////////////////////////////////
Route::get('/Login', [StudentController::class, 'Login']);

//////////////////////////////////////////////////////~api/Student///////////////////////////////////////////////
Route::prefix('Student')->group(function () {
    Route::get('/FullTimetable', [StudentController::class, 'FullTimetable']);
    Route::get('/Notification', [StudentController::class, 'Notification']);
    Route::get('/Transcript', [StudentController::class, 'Transcript']);
    Route::get('/attendance', [StudentController::class, 'getAttendance']);
    Route::get('/attendancePerSubject', [StudentController::class, 'AttendancePerSubject']);
    Route::post('/submitTask', [StudentController::class, 'submitAnswer']);
    Route::post('/update-password', [StudentController::class, 'updatePassword']);
    Route::post('/update-student-image', [StudentController::class, 'updateStudentImage']);
    Route::get('/current-enrollments', [StudentController::class, 'StudentCurrentEnrollmentsName']);
    Route::get('/all-enrollments', [StudentController::class, 'StudentAllEnrollmentsName']);
    Route::get('/task/details', [StudentController::class, 'getTaskDetails']);
    Route::get('/subject/task-result', [StudentController::class, 'GetSubjectTaskResult']);
    Route::get('/course-content', [StudentController::class, 'GetFullCourseContentOfSubject']);
    Route::get('/course-content/week', [StudentController::class, 'GetFullCourseContentOfSubjectByWeek']);
    Route::post('/contest-attendance', [StudentController::class, 'ContestAttendance']);
    Route::get('/getActiveEnrollments', [StudentController::class, 'getActiveEnrollments']);
    Route::get('/getPreviousEnrollments', [StudentController::class, 'getYourPreviousEnrollments']);
    Route::get('/TranscriptSessionDropDown', [StudentController::class, 'TranscriptSessionDropDown']);
    Route::get('/get/notification', [StudentController::class, 'Notifications']);
    
    //18
});
//////////////////////////////////////////////////////~api/Grader////////////////////////////////////////////////
Route::prefix('Grader')->group(function () {
    Route::get('/GraderInfo', [GraderController::class, 'GraderOf']);
    Route::get('/YourTask', [GraderController::class, 'GraderTask']);
    Route::get('/ListOfStudent', [GraderController::class, 'ListOfStudentForTask']);
    Route::post('/SubmitTaskResult', [GraderController::class, 'SubmitNumber']);
    Route::post('/SubmitTaskResultList', [GraderController::class, 'SubmitNumberList']);
    //5
});
//////////////////////////////////////////////////////~api/JuniorLecturer////////////////////////////////////////////////
Route::prefix('JuniorLecturer')->group(function () {
    Route::get('/full-timetable', [JuniorLecturerController::class, 'FullTimetable']);
    Route::get('/your-courses', [JuniorLecturerController::class, 'YourCourses']);
    Route::get('/notifications', [JuniorLecturerController::class, 'YourNotification']);
    Route::post('/send-notification', [JuniorLecturerController::class, 'sendNotification']);
    Route::get('/dropdown/active-courses', [JuniorLecturerController::class, 'ActiveCourseInfo']);
    Route::get('/get/tasks', [JuniorLecturerController::class, 'getTaskInfo']);
    Route::get('/task-submissions', [JuniorLecturerController::class, 'getTaskSubmissionList']);
    Route::post('/submit-number', [JuniorLecturerController::class, 'SubmitNumber']);
    Route::post('/submit-number-list', [JuniorLecturerController::class, 'SubmitNumberList']);
    Route::get('/attendance-list-lab', [JuniorLecturerController::class, 'attendanceListofLab']);
    Route::get('/attendance-list/student', [JuniorLecturerController::class, 'attendanceListofSingleStudent']);
    Route::post('/tasks/store', [JuniorLecturerController::class, 'storeTask']);
    Route::get('/lab-attendance-list', [JuniorLecturerController::class, 'getLabAttendanceList']);
    // Route for marking single attendance record
    Route::post('/attendance/mark-single', [JuniorLecturerController::class, 'markSingleAttendance']);
    // Route for marking multiple attendance records (bulk)
    Route::post('/attendance/mark-bulk', [JuniorLecturerController::class, 'markBulkAttendance']);
    Route::get('/today-lab-classes', [JuniorLecturerController::class, 'getTodayLabClassesWithTeacherCourseAndVenue']);
    //16
});





//////////////////////////////////////////////////////~api/Admin///////////////////////////////////////////////
Route::prefix('Admin')->group(function () {
    Route::get('/AllStudent', [AdminController::class, 'AllStudent']);
    Route::post('/SendNotification', [AdminController::class, 'sendNotification']);
    Route::get('/sections', [AdminController::class, 'showSections']);
    Route::get('/teachers', [AdminController::class, 'AllTeacher']);
    Route::get('/courses', [AdminController::class, 'AllCourse']);
    Route::get('/grades', [AdminController::class, 'AllGrades']);
    Route::get('/teacher-graders', [AdminController::class, 'getAllTeacherGraders']);
    Route::get('/sessions', [AdminController::class, 'getAllSessions']);
    Route::get('/junior-lectures', [AdminController::class, 'allJuniorLecturers']);
    Route::get('/course-content', [AdminController::class, 'getCourseContent']);
    Route::get('/search-admin', [AdminController::class, 'searchAdminByName']);
    Route::get('/search-datacell', [AdminController::class, 'GetDatacell']);
    //12
});

//////////////////////////////////////////////////////~api/Teacher///////////////////////////////////////////////
Route::prefix('Teacher')->group(function () {
    Route::post('/markAttendance', [TeacherController::class, 'markAttendance']);
    Route::get('/FullTimetable', [TeacherController::class, 'FullTimetable']);


    /////////////////////////////////////////SharjeelCodeRoutes/////////////////////////////////////////////
    Route::get('classestoday/{teacher_id}', [TeacherController::class, 'getTodayClasses']);
    Route::get('/venues', [TeacherController::class, 'getAllVenues']);
    Route::get('/currentcourses/{teacher_id}', [TeacherController::class, 'getCurrentOfferedCourses']);
    Route::get('/courses/{teacher_id}', [TeacherController::class, 'getAllOfferedCourses']);
    Route::post('/markAttendance', [TeacherController::class, 'markAttendance']);
    Route::get('/FullTimetable', [TeacherController::class, 'FullTimetable']);
    Route::post('/sendNotification', [TeacherController::class, 'sendNotification']);
    Route::get('/Attendence', [TeacherController::class, 'getStudentsByTeacherAndSection']);
    Route::get('/sortAttendence', [TeacherController::class, 'getSortedAttendance']);
    Route::get('/teacher-course-details', [TeacherController::class, 'getCourseDetails']);
    //12
});

////////////////////////////////////////////////////////~TESTING~///////////////////////////////

Route::get('/checking', [TestController::class, 'Empty']);

Route::get('/good', [TestController::class, 'upload']);

//////////////////////////////////////////////////////~api/Datacell////////////////////////////////////////////////

Route::prefix('Datacell')->group(function () {
    ////~Archives~/////////
    Route::get('/getArchivesDetails', [DatacellController::class, 'Archives']);
    Route::delete('/DeleteFolderByPath', [DatacellController::class, 'DeleteFolderByPath']);
    ///~Excel-Upload~//////
    Route::post('/excel/offered-course', [DatacellController::class, 'OfferedCourseTeacheruploadExcel']);
    Route::post('/excel/session-timetable', [DatacellController::class, 'UploadTimetableExcel']);
    Route::post('/excel/student-enrollment', [DatacellController::class, 'UploadStudentEnrollments']);
    Route::post('/excel/add-or-update-student', [DatacellController::class, 'AddOrUpdateStudent']);
    Route::post('/excel/add-or-update-courses', [DatacellController::class, 'AddOrUpdateCourses']);
    Route::post('/excel/add-or-update-teacher', [DatacellController::class, 'AddOrUpdateTeachers']);
    Route::post('/excel/add-or-update-juniorLecturers', [DatacellController::class, 'AddOrUpdateJuniorLecturers']);
    Route::post('/excel/graders-assign', [DatacellController::class, 'assignGrader']);
    Route::post('/excel/assign-junior-lecturer', [DatacellController::class, 'assignJuniorLecturer']);
    ///~View-Full~////////
    Route::post('/EnrollStudent', [DatacellController::class, 'NewEnrollment']);
    Route::get('/timetable/section', [DatacellController::class, 'getTimetableGroupedBySection']);
    Route::get('/AllStudent', [DatacellController::class, 'AllStudent']);
    Route::post('/NewOfferedCourse', [DatacellController::class, 'AddNewOfferedCourse']);
    Route::post('/send/notification', [DatacellController::class, 'sendNotification']);

    
    //14
});
