<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;
use Google\Auth\ApplicationDefaultCredentials;
use Exception;
use Google\Auth\Credentials\ServiceAccountCredentials;


trait SendNotificationTrait
{
    public function sendRichNotification($token, $title, $body, $imageUrl = null, $data = [])
    {
        try {
            $fcmUrl = "https://fcm.googleapis.com/v1/projects/lmsv1-e1686/messages:send";
            $notification = [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'android' => [
                    'notification' => [
                        'icon' => 'ic_notification',
                        'color' => '#3969D7',
                        'sound' => 'default',
                    ],
                ],
                'data' => (array) $data,
                'token' => $token
            ];
            if (!empty($imageUrl)) {
                $notification['notification']['image'] = $imageUrl;
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($fcmUrl, ['message' => $notification]);

            if ($response->failed()) {
                throw new Exception("Firebase Rich Notification Failed: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function sendNotification($token, $title, $body, $data = [])
    {
        try {
            $fcmUrl = "https://fcm.googleapis.com/v1/projects/lmsv1-e1686/messages:send";
            $notification = [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                // 'data' => (array) $data, // Ensure 'data' is an associative array
                'token' => $token
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
            ])->post($fcmUrl, ['message' => $notification]);

            if ($response->failed()) {
                throw new Exception("Firebase Notification Failed: " . $response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    public function sendFCMNotification()
    {
        $serviceAccountPath = config('services.firebase.key_path'); // Path to your JSON key
        $notification = [
            'notification' => [
                'title' => 'Test',
                'body' => 'I,M TESTING',
            ],
            // 'data' => (array) $data, // Ensure 'data' is an associative array
            'token' => 'cI_BoBt_T668K0-gH-1gqF:APA91bHrcI4KsWQrsKY_wCk5Ba3b4Bo5BvZvjrrst-hijLtb2GDtgha7PeFbjwAj-7-Pm0V3evpjwNlCBF2JMponmcYdZ6rQQ7Tn6e1G1VD9Idvf5EGKFWE'
        ];
        // Define OAuth 2.0 scope for FCM
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        // Authenticate with the service account
        $googleAuth = new ServiceAccountCredentials($scopes, $serviceAccountPath);
        $googleAuth->fetchAuthToken();
        $accessToken = $googleAuth->getLastReceivedToken()['access_token'];

        // FCM v1 API URL
        
        $fcmUrl = "https://fcm.googleapis.com/v1/projects/lmsv1-e1686/messages:send";;

        // Make API request with Bearer Token
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
            'Content-Type' => 'application/json',
        ])->post($fcmUrl, [
                    'message' => $notification
                ]);

        return $response->json();
    }
    private function getAccessToken()
    {
        try {
            $keypath = config('services.firebase.key_path');
            putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $keypath);
            $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];
            $credentials = ApplicationDefaultCredentials::getCredentials($scopes);
            $token = $credentials->fetchAuthToken();
            if (empty($token['access_token'])) {
                return null;
            }
            return $token['access_token'];
        } catch (Exception $e) {
            return null;
        }
    }
}
