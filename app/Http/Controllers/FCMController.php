<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Intervention\Image\Image;
use App\Models\user_fcm_tokens;
use Kreait\Firebase\Messaging\CloudMessage;
use Intervention\Image\ImageManager;

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
    public function sendN()
    {
        try {
            $notification = [
                'notification' => [
                    'title' => 'Test',
                    'body' => 'I,M TESTING',
                ],
                // 'data' => (array) $data, // Ensure 'data' is an associative array
                'token' => 'cI_BoBt_T668K0-gH-1gqF:APA91bHrcI4KsWQrsKY_wCk5Ba3b4Bo5BvZvjrrst-hijLtb2GDtgha7PeFbjwAj-7-Pm0V3evpjwNlCBF2JMponmcYdZ6rQQ7Tn6e1G1VD9Idvf5EGKFWE'
            ];
                $serviceAccountPath = storage_path('app/lmsv1-e1686-firebase-adminsdk-fbsvc-d0b69729a1'); // Secure Path
            
                $firebase = (new Factory)
                    ->withServiceAccount($serviceAccountPath)
                    ->createMessaging();
            
                $message = CloudMessage::fromArray([
                    'token' => 'cI_BoBt_T668K0-gH-1gqF:APA91bHrcI4KsWQrsKY_wCk5Ba3b4Bo5BvZvjrrst-hijLtb2GDtgha7PeFbjwAj-7-Pm0V3evpjwNlCBF2JMponmcYdZ6rQQ7Tn6e1G1VD9Idvf5EGKFWE', // Replace with actual FCM token
                    'notification' => $notification,
                ]);
            
                return $firebase->send($message);
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            return response()->json(['error' => 'Firebase Messaging Error', 'message' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'An Unexpected Error Occurred', 'message' => $e->getMessage()], 500);
        }
    }
}
