<?php

use App\Http\Controllers\Controller;
use App\Http\Controllers\GraderController;
use App\Http\Controllers\LogicController;
use App\Http\Controllers\TestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\JuniorLecController;
use App\Http\Controllers\DatacellController;
use App\Http\Controllers\DatacellModuleController;
use App\Http\Controllers\TeacherModuleController;
use App\Http\Controllers\TeachersController;
use App\Http\Controllers\StudentsController;

Route::get('/load-file', [TeachersController::class, 'LoadFile']);
Route::get('/Login', [StudentsController::class, 'Login']);
Route::prefix('Students')->group(function () {
    Route::get('/Transcript', [StudentsController::class, 'Transcript']);
    Route::get('/TranscriptPDF', [StudentsController::class, 'getTranscriptPdf']);
    Route::get('/FullTimetable', [StudentsController::class, 'FullTimetable']);
    Route::get('/Notification', [StudentsController::class, 'Notification']);
    Route::get('/attendance', [StudentsController::class, 'getAttendance']);
    Route::get('/attendancePerSubject', [StudentsController::class, 'AttendancePerSubject']);
    Route::get('/get/notification', [StudentsController::class, 'Notifications']);
    Route::post('/update-password', [StudentsController::class, 'updatePassword']);
    Route::post('/update-student-image', [StudentsController::class, 'updateStudentImage']);
    Route::get('/current-enrollments', [StudentsController::class, 'StudentCurrentEnrollmentsName']);
    Route::get('/all-enrollments', [StudentsController::class, 'StudentAllEnrollmentsName']);
    Route::post('/contest-attendance', [StudentsController::class, 'ContestAttendance']);
    Route::get('/getActiveEnrollments', [StudentsController::class, 'getActiveEnrollments']);
    Route::get('/getPreviousEnrollments', [StudentsController::class, 'getYourPreviousEnrollments']);
    Route::get('/TranscriptSessionDropDown', [StudentsController::class, 'TranscriptSessionDropDown']);
    Route::get('/subject/task-result', [StudentsController::class, 'GetSubjectTaskResult']);
    Route::get('/subject/task-considered', [StudentsController::class, 'getTaskConsiderations']);
    Route::get('/task/details', [StudentsController::class, 'getTaskDetails']);
    Route::get('/course-content', [StudentsController::class, 'GetFullCourseContentOfSubject']);
    Route::get('/course-content/week', [StudentsController::class, 'GetFullCourseContentOfSubjectByWeek']);
    Route::post('/submitTask', [StudentsController::class, 'submitAnswer']);
});
Route::prefix('Grader')->group(function () {
    Route::get('/GraderInfo', [GraderController::class, 'GraderOf']);
    Route::get('/YourTask', [GraderController::class, 'GraderTask']);
    Route::get('/ListOfStudent', [GraderController::class, 'ListOfStudentForTask']);
    Route::post('/SubmitTaskResult', [GraderController::class, 'SubmitNumber']);
    Route::post('/SubmitTaskResultList', [GraderController::class, 'SubmitNumberList']);
});
Route::prefix('JuniorLec')->group(function () {
    Route::get('classestoday/{juniorLecturerId}', [JuniorLecController::class, 'juniorTodayClassesWithStatus']);
    Route::get('/full-timetable', [JuniorLecController::class, 'FullTimetable']);
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
    Route::get('/contest-list', [JuniorLecController::class, 'ContestList']);
    Route::post('/process-contest', [JuniorLecController::class, 'ProcessContest']);
    Route::get('/jl-unassigned-task', [JuniorLecController::class, 'getListofUnassignedTask']);
    Route::post('/temporary-enrollment', [JuniorLecController::class, 'AddRequestForTemporaryEnrollment']);
});
Route::prefix('Teachers')->group(function () {
    Route::post('/add-or-update-feedbacks', [TeachersController::class, 'AddFeedback']);
    Route::post('/copy/previousSemesterCourseContent', [TeachersController::class, 'CopySemester']);
    Route::post('/update/course-content-topic-status', [TeachersController::class, 'updateCourseContentTopicStatus']);
    Route::get('/sections/info', [TeachersController::class, 'getSectionList']);
    Route::get('/section-task-result', [TeachersController::class, 'getSectionTaskResult']);
    Route::get('/get-single-student-task-result', [TeachersController::class, 'getSingleStudentTaskResult']);
    Route::get('/section-attendance-list', [TeachersController::class, 'getAttendanceBySubjectForAllStudents']);
    Route::get('/venues', [TeachersController::class, 'getAllVenues']);
    Route::get('/FullTimetable', [TeachersController::class, 'FullTimetable']);
    Route::get('classestoday/{teacher_id}', [TeachersController::class, 'getTodayClassesWithAttendanceStatus']);
    Route::get('/All-courses/{teacher_id}', [TeachersController::class, 'getAllOfferedCourses']);
    Route::get('/contest-list', [TeachersController::class, 'ContestList']);
    Route::post('/process-contest', [TeachersController::class, 'ProcessContest']);
    Route::get('/task/get', [TeachersController::class, 'YourTaskInfo']);
    Route::get('/tasks/unassigned-to-grader', [TeachersController::class, 'UnAssignedTaskToGrader']);
    Route::post('/tasks/assign-grader', [TeachersController::class, 'assignTaskToGrader']);
    Route::get('/teacher-graders', [TeachersController::class, 'getAssignedGraders']);
    Route::get('/teacher-unassigned-task', [TeachersController::class, 'getListofUnassignedTask']);
    Route::post('/store-task', [TeachersController::class, 'storeTask']);
    Route::post('/consider-task', [TeachersController::class, 'storeOrUpdateTaskConsiderations']);
    Route::post('/temporary-enrollment', [TeachersController::class, 'AddRequestForTemporaryEnrollment']);
    Route::post('/attendance/mark-single', [JuniorLecController::class, 'markSingleAttendance']);
    Route::post('/attendance/mark-bulk', [JuniorLecController::class, 'markBulkAttendance']);






    Route::get('/exam/section-result', [TeachersController::class, 'getSectionExamResult']);
});

Route::prefix('Teacher')->group(function () {
    Route::post('/markAttendance', [TeacherController::class, 'markAttendance']);
    Route::get('/currentcourses/{id}', [TeacherController::class, 'getCurrentOfferedCourses']);
    Route::post('/markAttendance', [TeacherController::class, 'markAttendance']);
    Route::post('/sendNotification', [TeacherController::class, 'sendNotification']);
    Route::get('/Attendence', [TeacherController::class, 'getStudentsByTeacherAndSection']);
    Route::get('/sortAttendence', [TeacherController::class, 'getSortedAttendance']);
    Route::get('/teacher-course-details', [TeacherController::class, 'getCourseDetails']);
});


















Route::prefix('Admins')->group(function () {

});
Route::prefix('Datacells')->group(function () {
    Route::get('/temporary-enrollments', [TeachersController::class, 'getTemporaryEnrollmentsRequest']);
    Route::post('/process-temporary-enrollments', [TeachersController::class, 'ProcessTemporaryEnrollments']);
});

//////////////////////////////////////////////////////~api/JuniorLecturer////////////////////////////////////////////////
Route::prefix('JuniorLecturer')->group(function () {
   
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
    Route::get('/Current-Courses/{sessionId}', [AdminController::class, 'getCoursesInCurrentSession']);
    Route::get('/Courses-Not-In-Session/{sessionId}', [AdminController::class, 'getCoursesNotInSession']);
    Route::get('/Teachers-With-No-Courses/{sessionId}', [AdminController::class, 'getTeachersWithNoCourses']);
    Route::get('/Teacher-Courses/{teacherId}/{sessionId}', [AdminController::class, 'getTeacherEnrolledCourses']);
    Route::get('/Students-Not-Enrolled-Courses/{sessionId}', [AdminController::class, 'getStudentsNotEnrolledInSession']);
    Route::get('/Student-Courses/{studentName}/{sessionId}', [AdminController::class, 'getStudentCoursesInSession']);
    Route::get('/failed-courses', [AdminController::class, 'getFailedCourses']);
    Route::get('/failed-students', [AdminController::class, 'getFailedStudents']);
    Route::get('/TeacherJLec', [AdminController::class, 'getTeacherJuniorLecturers']);
    Route::get('/assigned-graders', [AdminController::class, 'getTeachersWithAssignedGraders']);
    Route::get('/TeacherwithNoGrader', [AdminController::class, 'getTeachersWithoutGraders']);
    Route::get('/unassigned-graders', [AdminController::class, 'getUnassignedGraders']);
    Route::get('/Teacherfreeslots', [AdminController::class, 'noClassesToday']);
    Route::get('/TeacherJLecList', [AdminController::class, 'getAllTeachersWithJuniorLecturers']);
    Route::get('/history', [AdminController::class, 'getGraderHistory']);
    Route::post('/add-session', [AdminController::class, 'addSingleSession']);
});
Route::prefix('Datacell')->group(function () {
    Route::post('/EnrollStudent', [DatacellController::class, 'NewEnrollment']);
    Route::get('/timetable/section', [DatacellController::class, 'getTimetableGroupedBySection']);
    Route::get('/AllStudent', [DatacellController::class, 'AllStudent']);
    Route::post('/NewOfferedCourse', [DatacellController::class, 'AddNewOfferedCourse']);
    Route::post('/send/notification', [DatacellController::class, 'sendNotification']);
    Route::post('/send/notification/student', [DatacellModuleController::class, 'sendNotification']);
});

Route::prefix('Uploading')->group(function () {
    Route::post('/excel-upload/offeredcourse_teacherallocation', [DatacellModuleController::class, 'OfferedCourseTeacheruploadExcel']);
    Route::post('/excel-upload/excluded_days', [DatacellModuleController::class, 'ExcludedDays']);
    Route::post('/excel-upload/session', [DatacellModuleController::class, 'processSessionRecords']);
    Route::post('/excel-upload/venues', [DatacellModuleController::class, 'importVenues']);
    Route::post('/excel-upload/sections', [DatacellModuleController::class, 'importSections']);
    Route::post('/excel-upload/student-enrollments', [DatacellModuleController::class, 'uploadStudentEnrollments']);
    Route::post('/excel-uplaod/add-or-update-student', [DatacellModuleController::class, 'addOrUpdateStudent']);
    Route::post('/excel-upload/add-or-update-teacher', [DatacellModuleController::class, 'addOrUpdateTeacher']);
    Route::post('/excel-upload/upload-junior-lecturers', [DatacellModuleController::class, 'AddOrUpdateJuniorLecturers']);
    Route::post('/excel-uploading/graders-assign', [DatacellModuleController::class, 'assignGrader']);
    Route::post('/excel-upload/add-or-update-courses', [DatacellModuleController::class, 'AddOrUpdateCourses']);
    Route::post('/excel-upload/assign-juniorlecturer', [DatacellModuleController::class, 'assignJuniorLecturer']);
    Route::post('/uplaod/Exam', [DatacellModuleController::class, 'CreateExam']);
    Route::post('/uplaod/Topic', [DatacellModuleController::class, 'UploadCourseContentTopic']);
    Route::post('/uplaod/Result', [DatacellModuleController::class, 'UploadExamAwardList']);
    Route::post('/uplaod/timetable', [DatacellModuleController::class, 'UploadTimetableExcel']);
    Route::post('/timetable/section', [DatacellModuleController::class, 'getTimetableGroupedBySection']);
    Route::post('/uplaod/course-content', [DatacellModuleController::class, 'CreateCourseContent']);
    Route::get('/getArchivesDetails', [DatacellController::class, 'Archives']);
    Route::delete('/DeleteFolderByPath', [DatacellController::class, 'DeleteFolderByPath']);
    Route::post('/uplaod/subject-result', [DatacellModuleController::class, 'AddSubjectResult']);
});


