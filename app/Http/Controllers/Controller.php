<?php

namespace App\Http\Controllers;
use App\Models\Action;
use App\Models\notification;
use App\Models\student;
use App\Models\teacher_offered_courses;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use App\Models;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Models\session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use function Laravel\Prompts\select;
use Illuminate\Support\Str;
use App\Mail\ForgotPasswordMail;
use Illuminate\Support\Facades\Mail;
use App\Models\task;

abstract class Controller
{
    public function Sample(Request $request)
    {
        try {
            return response()->json(
                [
                    'message' => 'Fetched Successfully',
                ],
                200
            );
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid username or password'
            ], 404);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An unexpected error occurred',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
