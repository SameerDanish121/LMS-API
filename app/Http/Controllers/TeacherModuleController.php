<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\attendance;
use App\Models\contested_attendance;
use App\Models\Course;
use App\Models\coursecontent;
use App\Models\FileHandler;
use App\Models\grader;
use App\Models\grader_task;
use App\Models\notification;
use App\Models\offered_courses;
use App\Models\section;
use App\Models\student;
use App\Models\student_offered_courses;
use App\Models\student_task_result;
use App\Models\task_consideration;
use App\Models\teacher;
use App\Models\teacher_grader;
use App\Models\teacher_juniorlecturer;
use App\Models\teacher_offered_courses;
use App\Models\temp_enroll;
use App\Models\venue;
use Illuminate\Console\View\Components\Task;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;

class TeacherModuleController extends Controller
{
   
}
