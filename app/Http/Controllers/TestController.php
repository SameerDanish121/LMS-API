<?php

namespace App\Http\Controllers;

use App\Models\course;
use App\Models\section;
use App\Models\student_offered_courses;
use App\Models\teacher;
use Illuminate\Http\Request;
use App\Models\session;
USE Exception;
class TestController extends Controller
{
    public function Empty(Request $request)
    { 
        try {
            $task_id =(new course())->getIDByName('Technical And Business Writing');
                return response()->json(
                    [
                        'message' => 'Fetched Successfully',
                        'Course' => $task_id
                    ],
                    200
                );
            }
        catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
