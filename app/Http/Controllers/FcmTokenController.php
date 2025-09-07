<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\FirebaseException;

class FirebaseNotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        // Use the correct path to the service account file
        $serviceAccount = base_path('public/firebase/laizanew-33d585212b5e.json');

        try {
            // Create a Firebase instance
            $factory = (new Factory)->withServiceAccount($serviceAccount);
            $messaging = $factory->createMessaging();

            $message = [
                'notification' => [
                    'title' => $request->title,
                    'body' => $request->body,
                ],
                'token' => $request->fcm_token,
            ];

            // Define the message
            /* $message = [
                'notification' => [
                    'title' => 'Test title',
                    'body' => 'This is a test notification',
                ],
                'token' => 'c2VKcyF6RpiPL2Nel9IF_6:APA91bFkPsMMdPtmGJNJiCwNIh738ApIvT4_5bbJYXbI6fTKhsGhXCu6lStC6CpE_G90_C7pxTvs02_3u58L8ESbBXnwGPhNq8EQ3j_4B-K3s1SO6V0oda40pEeCZyoq8uIeT6TRPvwl',
            ];*/

            // Send the notification
            $messaging->send($message);

            // Return a successful response
            return response()->json(['message' => 'Notification sent successfully!']);
        } catch (FirebaseException $e) {
            // Return an error response
            return response()->json(['error' => 'Failed to send notification: ' . $e->getMessage()], 500);
        }
    }
}

