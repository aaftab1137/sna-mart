<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use App\Models\OtpVerification;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;


class LoginController extends BaseController
{

    public function getLocation(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'message' => $validator->errors()->first(),
            ], 400);
        }

        $latitude = $request->latitude;
        $longitude = $request->longitude;

        try {
            // Reverse Geocoding using OpenStreetMap API
            $response = Http::withHeaders([
                'User-Agent' => 'MarketPlaceApp/1.0'  // ✅ Prevents OpenStreetMap from blocking requests
            ])->timeout(10)->get("https://nominatim.openstreetmap.org/reverse", [
                'lat'    => $latitude,
                'lon'    => $longitude,
                'format' => 'json'
            ]);

            if ($response->successful()) {
                return response()->json([
                    'status'   => 200,
                    'location' => $response->json(),
                ], 200);
            } else {
                return response()->json([
                    'status'  => 500,
                    'message' => 'Could not fetch location details',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'Error fetching location',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    // public function sendOtp(Request $request)
    // {
    //     $validator = Validator::make($request->all(), [
    //         // 'phone_number' => 'required|digits:10'
    //         'email'            => 'required|email',

    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
    //     }

    //     $otp = rand(1000, 9999);
    //     $expiresAt = now()->addMinutes(5);

    //     // Save OTP to database
    //     $verification = OtpVerification::updateOrCreate(
    //         ['email' => $request->email],
    //         ['otp' => $otp, 'otp_expires_at' => $expiresAt]
    //     );

    //     // Send OTP via email or SMS
    //     try {
    //         $data = [
    //             'subject' => 'Your Verification OTP - Expires in 5 Minutes',
    //             'title'   => 'Your One-Time Password (OTP) for Login/Signup',
    //             'email'   => $verification->email,
    //             'page'    => 'email.signup_otp',
    //             'otp'     => $otp,
    //         ];

    //         User::send_mail($data);
    //     } catch (\Exception $e) {
    //         $errorMessage = $e->getMessage();

    //         if (str_contains($errorMessage, 'Daily user sending limit exceeded')) {
    //             return response()->json([
    //                 'status'  => 429,
    //                 'message' => 'Email limit exceeded. Please try again later or use another email.',
    //                 'error'   => true,
    //             ], 429);
    //         }

    //         return $this->sendError('Email sending failed.', $errorMessage);
    //     }
    //     // $this->sendSms($phone_number, "Your OTP is $otp. It expires in 5 minutes.");

    //     $output['status']  = 200;
    //     $output['message'] = 'OTP has been sent to your email for verification.';
    //     $output['email']   = $verification->email;

    //     return response()->json($output, $output['status']);
    // }

    // Verify OTP & Login/Register User
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            // 'phone_number' => 'required|digits:10',
            'mobile'            => 'required',
            'otp'               => 'required|digits:4',
            'fcm_token'  => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        $mobile = '91' . $request->mobile;
        $otp = $request->otp;

        // Check OTP in database
        $otpRecord = OtpVerification::where('mobile', $mobile)->first();

        if (!$otpRecord || $otpRecord->otp != $otp) {
            return response()->json(['status' => 400, 'message' => 'Invalid or expired OTP!'], 400);
        }

        // Check if user already exists
        $user = User::where('phone_number', $request->mobile)->first();

        if (!$user) {
            return response()->json([
                'status' => 201,
                'message' => 'OTP verified! Please provide your name and email to complete registration.',
                'mobile' => $request->mobile
            ]);
        }

        // Update FCM token if provided
        if ($user && $request->filled('fcm_token')) {
            $user->fcm_token = $request->fcm_token;
            $user->save();
        }
        // Existing User - Log them in
        $token = $user->createToken('MyApp')->accessToken;
        $user->is_verified = 1;
        $user->save();
        return response()->json(['status' => 200, 'message' => 'Login successful!', 'token' => $token, 'user' => $user]);
    }

    public function deleteFcmToken(Request $request)
    {

        $user = Auth::guard('api')->user();

        if (!$user) {
            return response()->json(['status' => 401, 'message' => 'Unauthorized.'], 401);
        }

        // Clear FCM token
        $user->fcm_token = null;
        $user->save();

        return response()->json([
            'status'  => 200,
            'message' => 'FCM token removed successfully.'
        ]);
    }

    public function sendOtpByMsg(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'mobile' => 'required|digits:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        $mobile = $request->mobile;
        $fullMobile = '91' . $mobile;
        $user = User::where('phone_number', $mobile)->first();
        $testMobile = '9999999999'; // test number
        $fixedOtp = '1234';

        if ($user && $user->is_blocked == 1) {
            return response()->json([
                'status' => 403,
                'message' => 'Your account has been blocked by the admin. Please contact support.'
            ], 403);
        }
        // Check if test number
        if ($mobile === $testMobile) {
            $verification = OtpVerification::updateOrCreate(
                ['mobile' => $fullMobile],
                ['otp' => $fixedOtp]
            );

            return response()->json([
                'message' => 'OTP sent successfully',
                'status' => 200,
                // 'test_mode' => true,
               // 'otp' => $fixedOtp
            ]);
        }

        $otp = rand(1000, 9999); 
        $expiresAt = now()->addMinutes(5);
        $mobile = '91' . $request->mobile;


        $verification = OtpVerification::where('mobile', $mobile)->first();

        if (!$verification) {
            $verification = new OtpVerification();
            $verification->mobile = $mobile;
        }

        $verification->otp = $otp;
        $verification->otp_expires_at = $expiresAt;
        $verification->save();

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post('https://control.msg91.com/api/v5/otp', [
                'template_id'       => '68653e16d6fc0504d32d6772',
                'authkey'           => '454746AkMzeOUQ68514869P1',
                'mobile'            => $mobile,
                'otp'               => $otp,
                'otp_expiry'        => 5, // Optional: expiry in minutes
                'Param1'            => $otp,
                // 'Param2'            => config('services.msg91.otp_expiry') . ' minutes',
            ]);

            if ($response->successful()) {

                return response()->json(['message' => 'OTP sent successfully', 'status' => 200]);
            } else {
                $msg91Response = $response->json();
                return response()->json([
                    'status'  => $response->status(),  // Pass through Msg91 HTTP status code
                    'message' => 'OTP sending failed',
                    'error'   => $msg91Response  // Return the original error from Msg91
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 500,
                'message' => 'Error sending otp try after some time',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function markAsSold(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        $product = Product::with('images')->where('id', $id)->where('user_id', $user->id)->first();

        if (!$product) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found.',
            ], 404);
        }

        // Define the default sold image (stored safely in S3)
        $soldImageUrl = rtrim(env('AWS_URL'), '/') . '/default/soldout.png';
        $defaultSoldImagePath = 'uploads/default/soldout.png';

        // Delete product's current main image if it's NOT the default soldout image
        if (!empty($product->product_image)) {
            $parsedUrl = parse_url($product->product_image);
            $s3Path = ltrim($parsedUrl['path'], '/');

            if ($s3Path !== $defaultSoldImagePath && Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
            }
        }

        // Delete all additional images from S3 and DB
        foreach ($product->images as $image) {
            $parsedUrl = parse_url($image->image_path);
            $s3Path = ltrim($parsedUrl['path'], '/');
            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
            }
            $image->delete();
        }

        // Set product image to fixed soldout image
        $product->is_sold = true;
        $product->product_image = $soldImageUrl;
        $product->save();

        return response()->json([
            'status' => 200,
            'message' => 'Product marked as sold out successfully.',
            'product' => $product
        ]);
    }
}
