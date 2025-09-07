<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController as BaseController;
use App\Models\MembershipPlan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\OtpVerification;
use App\Models\Rating;
use App\Models\UserMembership;
use App\Models\BoostingProduct;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\Notification;


class UserController extends BaseController
{
    public function profile()
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return $this->sendError('Unauthorized', [], 401);
            }


            $get_user = User::where(['id' => $user->id])->first();

            if (!$get_user) {
                return response()->json([
                    'message' => 'User not found',
                    'status' => 404,
                    'error' => true,
                ], 404);
            }

            $get_user->notification_count = Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->count();

            // $baseUrl = url('public/');
            // $defaultProfileImg = 'default/avatar.png';
            // $profileImgPath = $get_user->profile_img ? $get_user->profile_img : $defaultProfileImg;

            // $get_user->profile_img = $baseUrl . '/profiles/' . $profileImgPath;
            return $this->sendResponse([
                'user' => $get_user,
            ], 'Profile retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function createOrUpdateUser(Request $request)
    {
        try {
            // Check if the user exists (for update scenario)
            $user = User::where('email', $request->email)
                ->first();

            // Determine if this is an update
            $userId = $user ? $user->id : null;

            $validator = Validator::make($request->all(), [
                'name'          => 'required|string',
                'email'         => 'required|email|unique:users,email,' . $userId,
                'phone_number'  => 'required|digits:10|unique:users,phone_number,' . $userId,
                'profile_img'   => 'image|mimes:jpeg,png,jpg,gif,svg|max:4096',
                'location'      => 'nullable|string',
                'user_type'     => 'required|in:user',
                'lat'           => 'nullable|numeric|between:-90,90',
                'long'          => 'nullable|numeric|between:-180,180',
                'fcm_token'     => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
            }

            if ($user) {
                $message = 'User updated successfully!';
            } else {
                $user = new User();
                $message = 'User registered successfully!';
            }

            $user->name         = $request->name ?? $user->name;
            $user->email        = $request->email ?? $user->email;
            $user->phone_number = $request->phone_number ?? $user->phone_number;
            $user->location     = $request->location ?? $user->location;
            $user->user_type    = $request->user_type ?? $user->user_type;
            $user->lat          = $request->lat ?? $user->lat;
            $user->long         = $request->long ?? $user->long;
            $user->fcm_token    = $request->fcm_token ?? $user->fcm_token;

            if ($request->hasFile('profile_img')) {

                $directory = 'profiles';
                $user->profile_img = $this->uploadFile($request->file('profile_img'), $directory);
            }

            $user->is_verified = 1;
            $user->is_profile_complete = 1;
            $user->save();
            $token = $user->createToken('MyApp')->accessToken;

            return response()->json(['status' => 200, 'message' => $message, 'token' => $token, 'user' => $user]);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'message' => 'Error.', 'error' => $e->getMessage()], 500);
        }
    }

    private function uploadFile($file, $directory)
    {
        try {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($directory, $fileName, 's3');

            // Make it public (optional, based on use-case)
            $path = $file->storeAs($directory, $fileName, 's3');
            Storage::disk('s3')->setVisibility($path, 'public');

            // Return full URL to access the image
            return Storage::disk('s3')->url($path);
        } catch (\Exception $e) {
            throw new \Exception('File upload to S3 failed: ' . $e->getMessage());
        }
    }

    public function userDelete(Request $request)
    {
        $user = Auth::guard('api')->user();


        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        OtpVerification::where('email', $user->email)->delete();

        if (!empty($user->profile_img)) {
            // Extract relative path from full URL
            $parsedUrl = parse_url($user->profile_img);
            $s3Path = ltrim($parsedUrl['path'], '/'); // remove leading slash

            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
            }
        }

        $user  =  $user->delete();

        if ($user) {
            return $this->sendResponse([], 'User deleted successfully');
        } else {
            return $this->sendError('Failed to delete user. Please try again.', [], 500);
        }
    }

    public function submitRating(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        $rating = Rating::updateOrCreate(
            ['user_id' => $user->id],
            ['rating' => $request->rating, 'feedback' => $request->feedback]
        );

        return response()->json(['status' => 200, 'message' => 'Rating submitted successfully!', 'rating' => $rating]);
    }

    public function getRatings()
    {
        $ratings = Rating::with('user:id,name')->latest()->get();
        return response()->json(['status' => 200, 'ratings' => $ratings]);
    }

    public function purchaseMembershipPlan(Request $request)
    {
        try {

            $user = Auth::guard('api')->user();

            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|exists:membership_plans,id',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
            }

            $plan = MembershipPlan::find($request->plan_id);

            $userMembership = UserMembership::where('user_id', $user->id)
                ->where('expires_at', '>', now()) // Active membership
                ->first();

            $remainingDays = 0;

            if ($userMembership) {
                // Calculate remaining days before expiry
                $remainingDays = now()->diffInDays($userMembership->expires_at);
                $message = 'Membership updated successfully!';
            } else {
                $message = 'Membership purchased successfully!';
            }

            $newExpiryDate = now()->addDays($plan->duration + $remainingDays);

            $purchasedPlans = UserMembership::updateOrCreate(
                ['user_id' => $user->id],
                ['plan_id' => $plan->id, 'price' => $plan->price, 'expires_at' => $newExpiryDate]
            );

            return response()->json([
                'message'     => $message,
                'status'      => 200,
                'membership'  => $purchasedPlans,
            ]);
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function viewMyActivePlan()
    {

        $user = Auth::guard('api')->user();

        $membership = UserMembership::where('user_id', $user->id)
            ->where('expires_at', '>', now())->with(['plans', 'users:id,name,email'])->first();

        if (!$membership) {
            return response()->json([
                'message' => 'No Active Membership. Please purchase a one.',
                'status'  => 200,
            ]);
        }

        return response()->json([
            'message'      => 'Active Membership',
            'status'       => '200',
            'membership'   => $membership
        ]);
    }

    public function viewMyAllActivePlans()
    {

        $user = Auth::guard('api')->user();

        $membership = UserMembership::where('user_id', $user->id)
            ->where('expires_at', '>', now())->with(['plans', 'users:id,name,email'])->get();

        $boostedProducts = BoostingProduct::where('user_id', $user->id)
            ->where('boosted_until', '>', now())
            //->select('id', 'product_id', 'boosting_plan_id', 'days', 'boosted_until') // Include user_id
            ->with(['product:id,title,product_image,price,location', 'boostingPlan'])
            ->get();

        $baseUrl = url('public/');
        $boostedProducts->transform(function ($product) use ($baseUrl, $user) {

            if (!empty($product->product->product_image)) {
                $product->product->product_image = $baseUrl . '/' . $product->product->product_image;
            }

            return $product;
        });

        return response()->json([
            'message'      => 'All Active Plans',
            'status'       => '200',
            'membershipPlan'   => $membership,
            'boostingPlan'     => $boostedProducts,
        ]);
    }

    public function downloadMembershipInvoice(Request $request, $membershipId)
    {
        $user = Auth::guard('api')->user();

        $membership = UserMembership::where('id', $membershipId)
            ->where('user_id', $user->id)
            ->with('plans')
            ->first();

        if (!$membership) {
            return response()->json(['status' => 404, 'message' => 'Membership not found'], 404);
        }
        $purchaseDate = Carbon::parse($membership->created_at)->format('d-m-Y');
        $expiryDate = Carbon::parse($membership->expires_at)->format('d-m-Y');

        $data = [
            'user'         => $user,
            'membership'   => $membership,
            'plan'         => $membership->plans,
            'purchase_date' => $purchaseDate,
            'expiry_date'  => $expiryDate,
        ];


        $pdf = Pdf::loadView('invoices.membership_invoice', $data);

        return $pdf->download('membership_invoice.pdf');
    }
}
