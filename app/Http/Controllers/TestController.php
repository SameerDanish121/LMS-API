<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\session;
USE Exception;
class TestController extends Controller
{
    public function Empty(Request $request)
    { 
        try {
            $task_id = (new session())->getUpcomingSessionId();
            if ($task_id) {
                return response()->json(
                    [
                        'message' => 'Fetched Successfully',
                        'SESSION' => $task_id
                    ],
                    200
                );
            } else {
                throw new Exception('No Session Found');
            }
        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
