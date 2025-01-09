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

////////////////////////////////////////////////////////~Comman Function~////////////////////////////////////////

Route::get('/Login', [StudentController::class, 'Login']);

Route::post('/ImageUpload', [TestController::class, 'upload']);


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
    
});

//////////////////////////////////////////////////////~api/Admin///////////////////////////////////////////////

Route::prefix('Admin')->group(function () {
});
Route::prefix('Teacher')->group(function () {
    Route::post('/markAttendance', [TeacherController::class, 'markAttendance']);
    Route::get('/FullTimetable', [TeacherController::class, 'FullTimetable']);

    /////////////////////////////////////////SharjeelCodeRoutes/////////////////////////////////////////////
    Route::get('classestoday/{teacher_id}', [TeacherController::class, 'getTodayClasses']);

    Route::get('/venues', [TeacherController::class, 'getAllVenues']);
    Route::get('/currentcourses/{teacher_id}', [TeacherController::class, 'getCurrentOfferedCourses']);
    Route::get('/courses/{teacher_id}', [TeacherController::class, 'getAllOfferedCourses']);
    Route::post('/markAttendance', [TeacherController::class,'markAttendance']);
    Route::get('/FullTimetable', [TeacherController::class,'FullTimetable']);
    Route::post('/sendNotification', [TeacherController::class, 'sendNotification']);
    Route::get('/Attendence', [TeacherController::class, 'getStudentsByTeacherAndSection']);
    Route::get('/sortAttendence', [TeacherController::class, 'getSortedAttendance']);
    Route::get('/teacher-course-details', [TeacherController::class, 'getCourseDetails']);



});

//////////////////////////////////////////////////////~api/JuniorLecturer////////////////////////////////////////////////


Route::prefix('JuniorLecturer')->group(function () {

});

//////////////////////////////////////////////////////~api/Grader////////////////////////////////////////////////

Route::prefix('Grader')->group(function () {
    Route::get('/GraderInfo', [GraderController::class, 'GraderOf']);
    Route::get('/YourTask', [GraderController::class, 'GraderTask']);
    Route::get('/ListOfStudent', [GraderController::class, 'ListOfStudentForTask']);
    Route::post('/SubmitTaskResult', [GraderController::class, 'SubmitNumber']);
});


//////////////////////////////////////////////////////~api/Datacell////////////////////////////////////////////////

Route::prefix('Datacell')->group(function () {
    Route::get('/AllStudent', [DatacellController::class, 'AllStudent']);
    Route::post('/NewOfferedCourse', [DatacellController::class, 'AddNewOfferedCourse']);
    Route::post('/EnrollStudent', [DatacellController::class, 'NewEnrollment']);
    Route::post('/UploadTeacherCourse', [DatacellController::class, 'OfferedCourseTeacheruploadExcel']);
    Route::post('/UploadTimetableExel', [DatacellController::class, 'UploadTimetableExcel']);
    Route::get('/timetable/section', [DatacellController::class, 'getTimetableGroupedBySection']);
    Route::get('/getArchivesDetails', [DatacellController::class, 'Archives']);
    Route::delete('/DeleteFolderByPath', [DatacellController::class, 'DeleteFolderByPath']);

});





////////////////////////////////////////////////////////~TESTING~///////////////////////////////

Route::get('/checking', [TestController::class, 'Empty']);

Route::post('/file-credentials', [TestController::class, 'getFileCredentials']);