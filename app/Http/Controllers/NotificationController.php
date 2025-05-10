<?php

namespace App\Http\Controllers;
use App\Models\FileHandler;
use App\Models\notification;
use App\Models\student;
use App\Models\user;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Messaging\CloudMessage;
use Google\Client as GoogleClient;
use App\Models\user_fcm_tokens;
class NotificationController extends Controller
{
    public function SendNotificationToStudent(Request $request)
    {
        try {
            $request->validate([
                'sender' => 'required|string',
                'sender_id' => 'required',
                'title' => 'required|string',
                'description' => 'required|string',
                'image' => 'nullable',
                'Broadcast' => 'nullable',
                'Student_Section' => 'nullable',
                'Student_id' => 'nullable',
            ]);


            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imagePath = FileHandler::storeFile(now()->timestamp, 'Notification', $request->file('image'));
                $imageUrl = $imagePath;
            }else if($request->has('image')){
                $imageUrl=$request->image;
            }

            $userId = [];
            $notification = new notification();
            $notification->title = $request->title;
            $notification->description = $request->description;
            $notification->url = $imageUrl;
            $notification->notification_date = now();
            $notification->sender = $request->sender;
            $notification->reciever = 'Student';
            $notification->TL_sender_id = $request->sender_id;

            if ($request->has('Broadcast') && $request->Broadcast == true) {
                $userId = student::pluck('user_id')->toArray();
                $notification->Brodcast = 1;
                $notification->Student_Section = null;
                $notification->TL_receiver_id = null;
            } else if ($request->has('Student_Section')) {
                $userId = student::where('section_id', $request->Student_Section)->pluck('user_id')->toArray();
                $notification->Brodcast = 0;
                $notification->Student_Section = $request->Student_Section;
                $notification->TL_receiver_id = null;
            } else if ($request->has('Student_id')) {
                $student = student::find($request->Student_id);
                if (!$student) {
                    return response()->json(['error' => 'Student not found'], 404);
                }
                $userId = [$student->user_id];
                $notification->Brodcast = 0;
                $notification->Student_Section = null;
                $notification->TL_receiver_id = $student->user_id;
            } else {
                return response()->json(['error' => 'Either Broadcast, Student_Section, or Student_id must be provided'], 422);
            }

            $notification->save();
            $notificationId = $notification->id;

            $title = $request->title;
            $description = $request->description;
            $type = 'general';

            // self::sendNotificationToUsers($userId, $title, $description, $imageUrl, $type, $notificationId);

            return response()->json(['message' => 'Notification sent successfully'], 200);
        } catch (\Exception $e) {
            \Log::error('SendNotificationToStudent Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to send notification', 'details' => $e->getMessage()], 500);
        }
    }

    public static function sendNotificationToUsers($userIds, $title, $body, $image = null, $type = null, $id = null)
    {
        try {
            // Fetch FCM tokens of the given users
            $tokens = user_fcm_tokens::whereIn('user_id', $userIds)
                ->pluck('fcm_token')
                ->unique()
                ->filter()
                ->toArray();

            if (empty($tokens)) {
                \Log::warning("No FCM tokens found for users: " . implode(',', $userIds));
                return;
            }

            // Loop through each token and send the notification
            foreach ($tokens as $fcmToken) {
                self::sendFCMNotification($fcmToken, $title, $body, $image, $type, $id);
            }

            \Log::info("Notification sent to " . count($tokens) . " tokens.");

        } catch (\Throwable $e) {
            \Log::error("sendNotificationToUsers Exception: " . $e->getMessage());
        }
    }
    public static function sendBulkFCMNotification(array $fcmTokens, $title, $body, $image = null, $type = null, $id = null)
    {
        foreach ($fcmTokens as $fcmToken) {
            self::sendFCMNotification($fcmToken, $title, $body, $image, $type, $id);
        }
    }
    public static function sendFCMNotification($fcmToken, $title, $body, $image = null, $type = null, $id = null)
    {
        try {
            $projectId = config('services.fcm.project_id');
            $credentialsFilePath = Storage::path('shhhhhmm.json');
            if (!file_exists($credentialsFilePath)) {
                // Silent fail (do not break app)
                \Log::error("Firebase credentials file not found at $credentialsFilePath");
                return;
            }
            $client = new GoogleClient();
            $client->setAuthConfig($credentialsFilePath);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
            $client->refreshTokenWithAssertion();
            $token = $client->getAccessToken();

            if (empty($token['access_token'])) {
                \Log::error("Failed to get Firebase access token");
                return;
            }
            $androidNotification = [
                "icon" => "ic_notification",
                "color" => "#3969D7",
                "sound" => "default",
            ];
            $message = [
                "token" => $fcmToken,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "android" => [
                    "priority" => "high",
                    "notification" => $androidNotification
                ],
                "data" => [
                    "type" => $type ?? "general",
                    "id" => $id ? (string) $id : "0",
                ],
            ];
            if ($image) {
                $message['notification']['image'] = $image;
            }

            $data = ["message" => $message];

            // Prepare Headers
            $headers = [
                "Authorization: Bearer {$token['access_token']}",
                'Content-Type: application/json'
            ];

            // Send Request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Prevent app blocking
            $response = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            // Log errors, do not break flow
            if ($err) {
                \Log::error("FCM Curl Error: " . $err);
            } else {
                $decoded = json_decode($response, true);
                if (isset($decoded['error'])) {
                    \Log::error("FCM Error: " . json_encode($decoded['error']));
                }
            }
        } catch (\Throwable $e) {
            // Catch everything without breaking
            \Log::error("FCM Exception: " . $e->getMessage());
        }
    }
    public function TestFirebase(Request $request)
    {
        $imageUrl = "https://img.freepik.com/free-vector/media-player-software-computer-application-geolocation-app-location-determination-function-male-implementor-programmer-cartoon-character_335657-1180.jpg?ga=GA1.1.1046342397.1717240298&semt=ais_hybrid";
        try {
            $request->validate([
                'fcm' => 'required',
            ]);
            $fcmToken = $request->fcm;
            $title = 'hello';
            $description = 'Good Bye';
            $projectId = config('services.fcm.project_id'); # INSERT COPIED PROJECT ID

            $credentialsFilePath = Storage::path('shhhhhmm.json');
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
                    "token" => $fcmToken,
                    "notification" => [
                        "title" => $title,
                        "body" => $description,
                        "image" => $imageUrl,
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
}


// $notification = [
//     'notification' => [
//         'title' => $title,
//         'body' => $body,
//     ],
//     'android' => [
//         'notification' => [
//             'icon' => 'ic_notification',
//             'color' => '#3969D7',
//             'sound' => 'default',
//         ],
//     ],
//     'data' => (array) $data,
//     'token' => $token
// ];