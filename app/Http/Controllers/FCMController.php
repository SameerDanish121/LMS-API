<?php

namespace App\Http\Controllers;

use App\Models\user_fcm_tokens;
use Illuminate\Http\Request;

class FCMController extends Controller
{
    public function storeFcmToken(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'required|exists:user,id',
                'fcm_token' => 'required|string',
            ]);

            $existingToken = user_fcm_tokens::where('user_id', $request->user_id)
                ->where('fcm_token', $request->fcm_token)
                ->first();

            if ($existingToken) {
                return response()->json([
                    'status' => 'ok',
                    'message' => 'FCM token already exists.',
                ], 200);
            }
            $userTokens = user_fcm_tokens::where('user_id', $request->user_id)->count();
            if ($userTokens >= 5) {
                // Remove the oldest token before inserting a new one
                user_fcm_tokens::where('user_id', $request->user_id)
                    ->orderBy('created_at', 'asc')
                    ->first()
                    ->delete();
            }

            user_fcm_tokens::create([
                'user_id' => $request->user_id,
                'fcm_token' => $request->fcm_token,
            ]);

            return response()->json([
                'status' => 'ok',
                'message' => 'FCM token stored successfully.',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Something went wrong.',
            ], 200);
        }
    }
    public static function getFcmTokensByUserId($user_id)
    {
        return user_fcm_tokens::where('user_id', $user_id)->pluck('fcm_token')->toArray();
    }
    public static function getFcmTokensByUserIds(array $userIds)
    {
        return user_fcm_tokens::whereIn('user_id', $userIds)
            ->pluck('fcm_token')
            ->toArray();
    }
    public static function sendRichNotificationToUsers(array $userIds, string $title, string $body, ?string $imageUrl = null,?array $data = [])
    {
        $responses = [];

        try {
            $fcmTokens = self::getFcmTokensByUserIds($userIds);

            if (empty($fcmTokens)) {
                return ['status' => 'error', 'message' => 'No FCM tokens found for the provided users.'];
            }
            $trait = new class {
                use \App\Traits\SendNotificationTrait;
            };
            foreach ($fcmTokens as $token) {
                try {
                   
                    $responses[] = $trait->sendRichNotification($token, $title, $body, $imageUrl, $data);
                } catch (\Exception $e) {
                    $responses[] = [
                        'status' => 'error',
                        'token' => $token,
                        'message' => 'Failed to send notification: ' . $e->getMessage(),
                    ];
                }
            }

            return $responses;
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => 'Unexpected error: ' . $e->getMessage()];
        }
    }
}
