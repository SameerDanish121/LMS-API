<?php

namespace App\Http\Controllers;
use App\Mail\ForgetPasswordEmail;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use App\Mail\PasswordChangeEmail;
use Illuminate\Support\Facades\Hash;

class AuthenticationController extends Controller
{
    public function sendOTP(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email'
            ]);
    
            $user = User::where('email', $request->email)->first();
    
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No user found with the given email.'
                ], 404);
            }
            $otp = rand(100000, 999999);
            $cacheKey = "otp_{$user->id}";
           
            if (Cache::has($cacheKey)) {
                $otp = Cache::get($cacheKey);
            } else {
                // Store OTP in cache for 2 minutes
                Cache::put($cacheKey, $otp, now()->addMinutes(2));
            }
          
            Mail::to($user->email)->send(new ForgetPasswordEmail($user->username, $otp));
    
            return response()->json([
                'status' => 'success',
                'user_id' => $user->id,
                'username' => $user->username,
                'message' => 'OTP has been sent to your email. Please enter the OTP within 2 minutes.'
            ]);
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
    public function verifyOTP(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'otp' => 'required|integer'
        ]);

        $cacheKey = "otp_{$request->user_id}";

        if (!Cache::has($cacheKey)) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP expired or invalid.'
            ], 400);
        }

        $cachedOTP = Cache::get($cacheKey);

        if ($cachedOTP == $request->otp) {
            // Remove OTP from cache after verification
            Cache::forget($cacheKey);

            return response()->json([
                'status' => 'success',
                'user_id' => $request->user_id,
                'message' => 'OTP verification successful.'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Invalid OTP.'
        ], 400);
    }
    public function updatePassword(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:user,id',
                'new_password' => [
                    'required',
                    'min:6',
                    'regex:/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d@$!%*?&]+$/', // At least one letter & one number
                ],
            ]); 
            $user = User::findOrFail($request->user_id);
            if ($user->password === $request->new_password) {
                return response()->json(['message' => 'You are already using this password. Choose a new one.'], 400);
            }
            $passwordExists = User::where('id', '!=', $user->id)
                ->where('password', $request->new_password)
                ->exists();
    
            if ($passwordExists) {
                return response()->json(['message' => 'This password has been used by another user. Choose a unique password.'], 400);
            }
            $user->password = $request->new_password;
            $user->save();
            Mail::to($user->email)->send(new PasswordChangeEmail($user->username, $request->new_password));
    
            return response()->json(['message' => 'Password updated successfully. A confirmation email has been sent.']);
    
        } catch (ValidationException $e) {
            return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['message' => 'Something went wrong.', 'error' => $e->getMessage()], 500);
        }
    }
}
