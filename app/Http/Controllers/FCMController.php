<?php

namespace App\Http\Controllers;
use App\Models\user;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Intervention\Image\Image;
use App\Models\user_fcm_tokens;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Messaging\CloudMessage;
use Google\Client as GoogleClient;

class FCMController extends Controller
{
    public function sendFcmNotification(Request $request)
    {
        $imageUrl = "https://img.freepik.com/free-vector/media-player-software-computer-application-geolocation-app-location-determination-function-male-implementor-programmer-cartoon-character_335657-1180.jpg?ga=GA1.1.1046342397.1717240298&semt=ais_hybrid";
        try {
            $request->validate([
                'user_id' => 'required|exists:user,id',
                'title' => 'required|string',
                'body' => 'required|string',
            ]);

            $user = user::find($request->user_id);
            $fcm = user_fcm_tokens::where('user_id', $request->user_id)->value('fcm_token');
            if (!$fcm) {
                return response()->json(['message' => 'User does not have a device token'], 400);
            }

            $title = $request->title;
            $description = $request->body;
            $projectId = config('services.fcm.project_id'); # INSERT COPIED PROJECT ID

            $credentialsFilePath = Storage::path('lmsv1-e1686-firebase-adminsdk-fbsvc-13b9b32671.json');
            if (!file_exists($credentialsFilePath)) {
                return response()->json([
                    'message' => 'Firebase credentials file not found.',
                    'error' => "File path: {$credentialsFilePath}"
                ], 500);
            }

            $client = new GoogleClient();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();

            $access_token = $token['access_token'];

            $headers = [
                "Authorization: Bearer $access_token",
                'Content-Type: application/json'
            ];

            $data = [
                "message" => [
                    "token" => $fcm,
                    "notification" => [
                        "title" => $title,
                        "body" => $description,
                        "image" => $imageUrl, // For rich notifications

                    ],
                    "android" => [
                        "priority" => "high",
                        "notification" => [
                           
                            "icon" => "ic_notification",
                            "color" => "#3969D7",
                            "sound" => "default"
                        ]
                    ],
                    "webpush" => [
                        "fcm_options" => [
                            "link" => $imageUrl
                        ]
                    ],
                    "data" => [
                        "type" => "chat",
                        "chat_id" => "12345"
                    ]


                ]
            ];
            $payload = json_encode($data);

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_VERBOSE, true); // Enable verbose output for debugging
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return response()->json([
                    'message' => 'Curl Error: ' . $err
                ], 500);
            } else {
                return response()->json([
                    'message' => 'Notification has been sent',
                    'response' => json_decode($response, true),
                    'fcm' => $fcm
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while sending the notification.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

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
    public static function sendRichNotificationToUsers(array $userIds, string $title, string $body, ?string $imageUrl = null, ?array $data = [])
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
