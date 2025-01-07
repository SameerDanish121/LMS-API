<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\course;
use App\Models\FileHandler;
use App\Models\section;
use App\Models\student_offered_courses;
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

        return FileHandler::deleteFileByPath($request->file);
    }

    public function Empty(Request $request)
    {
        try {
            $task_id = (new course())->getIDByName('Technical And Business Writing');
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
