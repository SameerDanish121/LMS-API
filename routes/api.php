<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\GraderController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\JuniorLecturerController;
use App\Http\Controllers\DatacellController;
Route::get('/Login', [StudentController::class, 'Login']);
Route::prefix('Student')->group(function () {
    Route::get('/FullTimetable', [StudentController::class, 'FullTimetable']);
    Route::get('/Notification', [StudentController::class, 'Notification']);
    Route::get('/Transcript', [StudentController::class, 'Transcript']);
    Route::get('/attendance', [StudentController::class,'getAttendance']);
    Route::get('/attendancePerSubject', [StudentController::class,'AttendancePerSubject']);
    Route::post('/submitTask', [StudentController::class,'submitAnswer']);
});
Route::prefix('Admin')->group(function () {
});
Route::prefix('Teacher')->group(function () {
    Route::post('/markAttendance', [TeacherController::class,'markAttendance']);
    Route::get('/FullTimetable', [TeacherController::class,'FullTimetable']);
});
Route::prefix('JuniorLecturer')->group(function () {

});
Route::prefix('Grader')->group(function () {
    Route::get('/GraderInfo', [GraderController::class,'GraderOf']);
    Route::get('/YourTask', [GraderController::class,'GraderTask']);
    Route::get('/ListOfStudent', [GraderController::class,'ListOfStudentForTask']);
    Route::post('/SubmitTaskResult', [GraderController::class,'SubmitNumber']);
});
Route::prefix('Datacell')->group(function () {
    Route::get('/AllStudent', [DatacellController::class,'AllStudent']);
    Route::post('/NewOfferedCourse', [DatacellController::class,'AddNewOfferedCourse']);
    Route::post('/EnrollStudent', [DatacellController::class,'NewEnrollment']);
});
