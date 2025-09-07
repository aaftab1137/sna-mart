<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification;
use App\Models\User;
use Validator;
use App\Services\NotificationService;

class NotificationController extends BaseController
{
    // send notification
    // public function sendNotification(Request $request, NotificationService $notifier)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [

    //             'user_id' => 'required|exists:users,id',
    //             'title' => 'required|string|max:255',
    //             'message' => 'required|string',
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json([
    //                 'message' => $validator->errors()->first(),
    //                 'status' => 400,
    //                 'error' => true,
    //             ], 422);
    //         }

    //         $user = User::find($request->user_id);

    //         $notification = new Notification();
    //         $notification->user_id  = $request->user_id;
    //         $notification->title    = $request->title;
    //         $notification->message  = $request->message;
    //         $notification->status   = 'unread';
    //         $notification->save();

    //         if ($user->fcm_token) {
    //             $notifier->sendNotification($request->title, $request->message, $user->fcm_token);
    //         }

    //         return response()->json([
    //             'message' => 'Notification sent successfully!',
    //             'notification' => $notification
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'message' => 'An error occurred while sending the notification.',
    //             'status'  => 500,
    //             'error'   => true,
    //             'data'    => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function sendNotification(Request $request, NotificationService $notifier)
    {
        try {
            $validator = Validator::make($request->all(), [
                'recipient' => 'required|string|in:user,all',
                'title'     => 'required|string|max:255',
                'message'   => 'required|string',
                'user_id'   => 'required_if:recipient,user|nullable|exists:users,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'status'  => 400,
                    'error'   => true,
                ], 422);
            }

            if ($request->recipient === 'user') {
                $user = User::find($request->user_id);

                $notification = new Notification();
                $notification->user_id  = $user->id;
                $notification->title    = $request->title;
                $notification->message  = $request->message;
                $notification->status   = 'unread';
                $notification->save();

                if ($user->fcm_token) {
                    $notifier->sendNotification($request->title, $request->message, $user->fcm_token);
                }
            }

            if ($request->recipient === 'all') {
                $users = User::where('user_type', '!=', 'admin')
                    ->where('is_blocked', 0)
                    ->get();

                foreach ($users as $user) {
                    // Save notification for every eligible user
                    $notification = new Notification();
                    $notification->user_id  = $user->id;
                    $notification->title    = $request->title;
                    $notification->message  = $request->message;
                    $notification->status   = 'unread';
                    $notification->save();

                    // Send only if user has FCM token
                    if ($user->fcm_token) {
                        $notifier->sendNotification($request->title, $request->message, $user->fcm_token);
                    }
                }
            }

            return response()->json([
                'message' => 'Notification sent successfully!',
                'status'  => 200,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while sending the notification.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
        }
    }


    // // Get Notifications for a User
    // public function getNotifications(Request $request)
    // {
    //     $user = Auth::guard('api')->user();
    //     $notifications = Notification::where('user_id', $user->id)
    //         ->orderBy('created_at', 'desc')
    //         ->get();

    //     Notification::where('user_id', $user->id)
    //         ->where('status', 'unread')
    //         ->update(['status' => 'read']);


    //     return response()->json([
    //         'message' => 'Notifications fetched successfully',
    //         'notifications' => $notifications
    //     ]);
    // }

    public function getNotifications(Request $request)
    {
        $user = Auth::guard('api')->user();

        
        $query = Notification::query();

        // Admin can view all, users can only view theirs
        if ($user->user_type != 'admin') {
            $query->where('user_id', $user->id);
        } else {
            // Optional filters for admin
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }
        }

        $query->orderBy('created_at', 'desc');

        // If paginate=true, apply pagination
        if ($request->get('paginate') == 'true') {
            $perPage = $request->get('per_page', 10);
            $notifications = $query->paginate($perPage);
        } else {
            $notifications = $query->get();
        }

        // $notifications = $query->orderBy('created_at', 'desc')->get();

        // Mark as read only if normal user
        if ($user->user_type != 'admin') {
            Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->update(['status' => 'read']);
        }

        return response()->json([
            'message' => 'Notifications fetched successfully',
            'notifications' => $notifications
        ]);
    }


    //delete notification
    public function deleteNotification($id)
    {
        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found!'], 404);
        }

        $notification->delete();

        return response()->json([
            'message' => 'Notification deleted successfully',
            'status' => 200,
            'error'  => false,
        ]);
    }

    public function deleteAllNotifications(Request $request)
    {
        $user = Auth::guard('api')->user();

        if ($user->user_type == 'admin') {

            $query = Notification::query();
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }
            $query->delete();
        } else {
            Notification::where('user_id', $user->id)->delete();
        }

        return response()->json(['message' => 'Notifications deleted successfully']);
    }


    public function updateNotificationStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:unread,read',
        ]);

        if ($validator->fails()) {
            return  response()->json([
                'message' => $validator->errors()->first(),
                'status'  => 400,
                'error'   => true,
            ], 422);
        }

        $notification = Notification::find($id);

        if (!$notification) {
            return response()->json(['error' => 'Notification not found!'], 404);
        }

        $notification->status = $request->status; // 'read' or 'unread'
        $notification->save();

        return response()->json([
            'message' => 'Notification status updated successfully',
            'notification' => $notification
        ]);
    }
}
