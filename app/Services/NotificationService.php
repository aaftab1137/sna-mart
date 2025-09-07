<?php
namespace App\Services;

use App\Models\FcmToken;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationService
{

protected $messaging;

// public function __construct()
// {
//     $this->firebase = (new Factory)
//         ->withServiceAccount(public_path('/firebase/snamart-7a97717eb720.json')) 
//         ->createMessaging();
// }

// public function sendNotificationToAllUsers($title, $body)
// {
//     // $tokens = FcmToken::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
//      $tokens = User::whereNotNull('fcm_token')->pluck('fcm_token')->toArray();
//     if (empty($tokens)) {
//         return false;
//     }

//     $notification = Notification::create($title, $body);

//     $message = CloudMessage::new()->withNotification($notification);
//     foreach (array_chunk($tokens, 500) as $chunk) {
//         $this->firebase->sendMulticast($message, $chunk);
//     }

//     return true;
// }

 function sendNotification($title, $body, $fcm_token)
    {
        $serviceAccount = base_path('public/firebase/snamart-7a97717eb720.json');

        try {
            // Create a Firebase instance
            $factory = (new Factory())->withServiceAccount($serviceAccount);
            $messaging = $factory->createMessaging();

            $message = [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'token' => $fcm_token,
                // 'token' => 'eZSSBxZhQ7i7XgMy3aVzcq:APA91bEatk4ULaGWstA7Ascc_uFH-J7wsoAYxvnlvoSgupr2AH1OF2s_W9pHhABnkzXxLtm3a76z1YF4qHJ4snP5_BufNnrX2S6uCXMnHl8mI9dccTWQrYk',
            ];

            // Send the message
            $messaging->send($message);

            return true; // Indicate success
        } catch (FirebaseException $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
            return false; // Indicate failure
        }
    }

}
