<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\course;
use App\Models\FileHandler;
use App\Models\quiz_questions;
use App\Models\section;
use App\Models\student_offered_courses;
use App\Models\task;
use App\Models\teacher;
use Illuminate\Http\Request;
use App\Models\session;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
class TestController extends Controller
{
    public function upload(Request $request)
    {
        try{
        $tasks = task::with(['courseContent', 'teacherOfferedCourse.offeredCourse.course'])->get();
        $task_details = $tasks->map(function ($task) {
            return [
                "id"=>$task->id,
                "type"=>$task->type,
                "start_date"=>$task->start_date,
                "end_date"=>$task->due_date,
                "created By"=>$task->CreatedBy,
                "points"=>$task->points,
                "title"=>$task->title,
                "Course"=>$task->teacherOfferedCourse->offeredCourse->course->name,
                "Section"=>(new section())->getNameByID($task->teacherOfferedCourse->section_id),
                "Marked Status"=>$task->isMarked?'Marked':'Un-Marked',
                "Pre Title"=>$task->courseContent->title,
                "For Week"=>$task->courseContent->week,
                "Pre Type"=>$task->courseContent->type,
                ($task->courseContent->content=='MCQS')?'MCQS':'File'=>($task->courseContent->content=='MCQS')
                ?self::getMCQS($task->courseContent->id)
                :FileHandler::getFileByPath($task->courseContent->content),
            ];
        });
        return $task_details;
        }catch(Exception $ex){
            return $ex->getMessage();
        }
    }
    public static function getMCQS($coursecontent_id){
       if(!$coursecontent_id){
        return null;
       }
       $Question=quiz_questions::where('coursecontent_id',$coursecontent_id)->with(['Options'])->get();
       if(!$Question){
        return null;
       }
       $Question_details=$Question->map(function($Question){
        return [
            "ID" => $Question->id,
            "Question NO" => $Question->question_no,
            "Question" => $Question->question_text,
            "Option 1" => $Question->Options[0]->option_text ?? null, 
            "Option 2" => $Question->Options[1]->option_text ?? null,
            "Option 3" => $Question->Options[2]->option_text ?? null,
            "Option 4" => $Question->Options[3]->option_text ?? null,
            "Answer" =>$Question->Options->firstWhere('is_correct', true)->option_text ?? null, 
        ];
       }
    );
       return $Question_details;
    }
    public function Empty(Request $request)
    {
        try {
            $task_id =TeacherModuleController::getActiveCoursesForTeacher(14);
            return response()->json(
                [
                    'message' => 'Fetched Successfully',
                    'Course' => $task_id
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
}
