<?php

namespace App\Http\Controllers;

use App\Models\student_offered_courses;
use Illuminate\Http\Request;
use App\Models\session;
USE Exception;
class TestController extends Controller
{
    public function Empty(Request $request)
    { 
        try {
            $task_id = student_offered_courses::GetCountOfTotalEnrollments(2);
                return response()->json(
                    [
                        'message' => 'Fetched Successfully',
                        'Enrolls' => $task_id
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
