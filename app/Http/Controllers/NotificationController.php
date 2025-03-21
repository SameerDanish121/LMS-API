<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Messaging\CloudMessage;
use Google\Client as GoogleClient;
use App\Models\user_fcm_tokens;
class NotificationController extends Controller
{
    
    public static function sendNotificationToUsers($userIds, $title, $body, $image = null, $type = null, $id = null)
    {
        try {
            // Fetch FCM tokens of the given users
            $tokens =user_fcm_tokens::whereIn('user_id', $userIds)
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
            $credentialsFilePath = Storage::path('lmsv1-e1686-firebase-adminsdk-fbsvc-13b9b32671.json');
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
                    "id" => $id ?? "0",
                ],
            ];
            if ($image) {
                $message['notification']['image'] = $image;
                $message['webpush'] = [
                    "fcm_options" => [
                        "link" => $image
                    ]
                ];
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
}
