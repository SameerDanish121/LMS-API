<?php

namespace App\Traits;
use Illuminate\Support\Facades\Http;
use Google\Auth\ApplicationDefaultCredentials;
use Exception;


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
                'Content-Type'  => 'application/json',
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
            $fcmUrl="https://fcm.googleapis.com/v1/projects/lmsv1-e1686/messages:send";
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
                'Content-Type'  => 'application/json',
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
