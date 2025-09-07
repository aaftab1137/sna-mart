<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Rating;
use App\Models\Product;
use App\Models\Wishlist;
use App\Models\ContactDetail;
use App\Models\SubscriptionSetting;
use App\Models\OtpVerification;
use App\Models\SponsoredLink;
use Illuminate\Support\Facades\Storage;
use App\Models\Notification;
use App\Services\NotificationService;
use Laravel\Passport\Token;

class AdminController extends BaseController
{

    // Admin Login
    public function adminLogin(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'email'     => 'required',
                'password'  => 'required',
                'user_type' => 'required|in:admin',
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        'status' => 400,
                        'message' => $validator->errors()->first(),
                    ],
                    402,
                );
            }

            if (Auth::attempt(['email' => $request->email, 'password' => $request->password, 'user_type' => $request->user_type])) {
                $user = Auth::user();
                $User = User::where(['id' => $user->id])->first();

                $token = $user->createToken('MyApp')->accessToken;

                $success['user']     = $User;
                $success['token']    = $token;


                return $this->sendResponse($success, 'Admin Successfully logged In');
            } else {
                return $this->sendError('Invalid Credentials', []);
            }
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    // All User
    public function getAllUsers(Request $request)
    {
        try {
            $query = User::where('user_type', 'user')->orderBy('created_at', 'desc');
            $perPage = $request->query('per_page', 10);

            if ($request->has('search') && $request->search != '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }
            
            // If paginate=true, apply pagination
            if ($request->get('paginate') == 'true') {
                $get_users = $query->paginate($perPage);
            } else {
                $get_users = $query->get();
            }

            return $this->sendResponse($get_users, 'All Users');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    // Approve reject product
    public function approveRejectProduct(Request $request, $id, NotificationService $notifier)
    {
        try {
            $validator = Validator::make($request->all(), [
                'is_approve' => 'required|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'status'  => 400,
                    'error'   => true,
                ], 422);
            }

            $productmodel = Product::find($id);
            $user = User::find($productmodel->user_id);

            if (!$productmodel) {
                return response()->json([
                    'message' => 'Product not found',
                    'status' => 404,
                    'error' => true,
                ], 404);
            }

            $productmodel->is_approve  = $request->is_approve;

            $productmodel->save();

            if ($productmodel->is_approve == '0') {
                Wishlist::where('product_id', $id)->delete();
                if ($user->fcm_token) {
                    $notifier->sendNotification('Product UnApproved', 'Your product "' . $productmodel->title . '" has been unapproved by the admin.', $user->fcm_token);
                }
                return $this->sendResponse([], 'Product unapproved successfully');
            } else {
                if ($user->fcm_token) {
                    $notifier->sendNotification('Product Approved', 'Congratulation!! Your product "' . $productmodel->title . '" has been approved by the admin.', $user->fcm_token);
                }
                return $this->sendResponse([], 'Product approved Successfully');
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while approving the product. Please try again later.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(), // For debugging
            ]);
        }
    }

    public function addUpdateContactDetail(Request $request)
    {

        $validator = Validator::make($request->all(), [

            'phone_number'  => 'required',
            'email'         => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        // Check if a contact detail already exists
        $contactDetail = ContactDetail::first();

        if ($contactDetail) {
            $contactDetail->update([
                'phone_number' => $request->phone_number,
                'email'        => $request->email,
            ]);
        } else {
            $contactDetail = ContactDetail::create([
                'phone_number' => $request->phone_number,
                'email'        => $request->email,
            ]);
        }

        return response()->json([
            'data'          => $contactDetail,
            'status'        => 200,
            'message'       => 'contact details saved successfully'
        ]);
    }

    public function getContactDetail(Request $request)
    {

        $contactDetails = ContactDetail::first();

        return $this->sendResponse($contactDetails, 'Contact details');
    }

    public function deleteContactDetail($id)
    {

        $contactDetail = ContactDetail::find($id);

        if (!$contactDetail) {
            return response()->json([
                'message'  => 'Contact Detail not found',
                'status'   => 404,
                'error'    => true,
            ], 404);
        }

        $contactDetail->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Contact Detail deleted successfully!'
        ]);
    }

    public function getSubscriptionSettings()
    {
        $setting = SubscriptionSetting::first();
        return response()->json([
            'status' => true,
            'data' => $setting
        ]);
    }

    public function updateSubscriptionSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_plan_active' => 'required|boolean',           // true = paid, false = free
            'product_upload_limit' => 'required|integer',
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status'  => 400,
                'error'   => true,
            ], 422);
        }

        $user = User::find($request->user_id);
        $user->is_plan_active = $request->is_plan_active;
        $user->product_upload_limit = $request->product_upload_limit;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Subscription settings updated successfully',
        ]);
    }

    public function userDelete($id)
    {

        $user = User::find($id);

        if (!$user) {
            return $this->sendError('User not found', [], 404);
        }

        $otpExists = OtpVerification::where('email', $user->email)->exists();
        if ($otpExists) {
            OtpVerification::where('email', $user->email)->delete();
        }

        if (!empty($user->profile_img)) {
            // Extract relative path from full URL
            $parsedUrl = parse_url($user->profile_img);
            $s3Path = ltrim($parsedUrl['path'], '/'); // remove leading slash

            if (Storage::disk('s3')->exists($s3Path)) {
                Storage::disk('s3')->delete($s3Path);
            }
        }

        if ($user->delete()) {
            return $this->sendResponse([], 'User deleted successfully');
        } else {
            return $this->sendError('Failed to delete user. Please try again.', [], 500);
        }
    }

    public function profile($id)
    {
        try {
            $user = Auth::guard('api')->user();

            // if (!$user) {
            //     return $this->sendError('Unauthorized', [], 401);
            // }
            $get_user = User::where(['id' => $id])->first();

            if (!$get_user) {
                return response()->json([
                    'message' => 'User not found',
                    'status' => 404,
                    'error' => true,
                ], 404);
            }

            $get_user->notification_count = Notification::where('user_id', $get_user->id)
                ->where('status', 'unread')
                ->count();

            return $this->sendResponse([
                'user' => $get_user,
            ], 'Profile retrieved successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function adminDashboard(Request $request)
    {
        try {
            $get_product_count = Product::all()->count();

            $get_active_user        = User::where(['user_type' => 'user', 'is_blocked' => 0])->count();
            $get_total_user        = User::where(['user_type' => 'user'])->count();
            $get_blocked_user        = User::where(['user_type' => 'user', 'is_blocked' => 1])->count();
            $get_active_product  = Product::where(['is_approve' => '1'])->count();
            $get_pending_product  = Product::where(['is_approve' => '0'])->count();

            $product_for_sell   = Product::where(['sell_or_rent' => 'sell'])->count();
            $product_for_rent = Product::where(['sell_or_rent' => 'rent'])->count();
            $get_sold_product = Product::where(['is_sold' => 1])->count();
            $get_rating_count = Rating::all()->count();


            $data = [
                'product_count'     => $get_product_count ?? 0,
                'Live Products'     => $get_active_product ?? 0,
                'pending_product'    => $get_pending_product ?? 0,
                'product_for_sell'   => $product_for_sell ?? 0,
                'product_for_rent'   => $product_for_rent ?? 0,
                'get_active_user'    => $get_active_user ?? 0,
                'get_total_user'    => $get_total_user  ?? 0,
                'get_blocked_user'    => $get_blocked_user  ?? 0,
                'get_sold_product'    => $get_sold_product ?? 0,
                'overall_rating_count'    => $get_rating_count ?? 0,

            ];

            return $this->sendResponse($data, 'Admin dashboard data retrieved successfully.');
        } catch (\Exception $e) {
            return $this->sendError('Error retrieving admin dashboard data.', $e->getMessage());
        }
    }

    public function addSponsoredLink(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'required|string|max:255',
            'link'  => 'required|url',
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => 400,
                'message' => $validator->errors()->first(),
                'error'   => true,
            ], 400);
        }

        $imagePath = $this->uploadFile($request->file('image'), 'uploads/sponsored_link_images');

        $sponsor = SponsoredLink::create([
            'name'  => $request->name,
            'link'  => $request->link,
            'image' => $imagePath,
        ]);

        return response()->json([
            'status'  => 200,
            'message' => 'Sponsored link added successfully.',
            'data'    => $sponsor,
        ]);
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

    public function getSponsoredLinks(Request $request)
    {
        try {
            $query = SponsoredLink::orderBy('id', 'desc');
            $perPage = $request->query('per_page', 10);

            if ($request->has('paginate') && $request->paginate == 'true') {
                $sponsoredLinks = $query->paginate($perPage);
            } else {
                $sponsoredLinks = $query->get();
            }

            return response()->json([
                'status' => 200,
                'message' => 'All Sponsored Links',
                'data' => $sponsoredLinks
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Failed to fetch sponsored links',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function updateSponsoredLink(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'link'  => 'required|url',
                'image' => 'nullable|mimes:jpg,jpeg,png|max:2048',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'status'  => 400,
                    'error'   => true,
                ], 400);
            }

            $sponsored = SponsoredLink::find($id);

            if (!$sponsored) {
                return response()->json([
                    'message' => 'Sponsored link not found',
                    'status' => 404,
                    'error' => true,
                ], 404);
            }

            $sponsored->name = $request->name ?? $sponsored->name;
            $sponsored->link  = $request->link ?? $sponsored->link;


            if ($request->hasFile('image')) {
                $oldImagePath = public_path('uploads/sponsored_links' . basename($sponsored->image));
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }

                // Upload and store the new image
                $sponsored->image = $this->uploadFile($request->file('image'), 'uploads/sponsored_links');
            }

            $sponsored->save();

            return response()->json([
                'data'    => $sponsored,
                'message' => 'Sponsored link updated successfully',
                'status'  => 200,
                'error'   => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function deleteSponsoredLink($id)
    {
        try {
            $sponsored = SponsoredLink::find($id);

            if (!$sponsored) {
                return response()->json([
                    'message' => 'Sponsored link not found',
                    'status' => 404,
                    'error' => true,
                ], 404);
            }

            // Delete image from S3 if exists
            if (!empty($sponsored->image)) {
                $parsedUrl = parse_url($sponsored->image);
                $s3Path = ltrim($parsedUrl['path'], '/');

                if (Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                }
            }

            $sponsored->delete();

            return response()->json([
                'message' => 'Sponsored link deleted successfully',
                'status'  => 200,
                'error'   => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to delete sponsored link.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(),
            ]);
        }
    }

    public function toggleBlockUser(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'is_blocked' => 'required|in:0,1'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        $user = User::find($id);

        if (!$user) {
            return response()->json(['status' => 404, 'message' => 'User not found.'], 404);
        }

        $user->is_blocked = $request->is_blocked;
        $user->save();

        if ($user->is_blocked == 1) {
            Token::where('user_id', $user->id)->update(['revoked' => true]);
            $user->fcm_token = null;
            $user->save();
        }

        return response()->json([
            'status' => 200,
            'message' => $request->is_blocked == 1 ? 'User has been blocked.' : 'User has been unblocked.',
            'user' => $user
        ]);
    }

    public function productDeleteByAdmin(Request $request, $id, NotificationService $notifier)
    {
        try {
            $product = Product::find($id);

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found.',
                    'status'  => 404,
                    'error'   => true,
                ], 404);
            }

            $user = User::find($product->user_id);

            $defaultSoldImageUrl = 'https://sna-img-prod.s3.amazonaws.com/uploads/default/sold_out.png';

            if (!empty($product->product_image) && $product->product_image !== $defaultSoldImageUrl) {
                $parsedUrl = parse_url($product->product_image);
                $s3Path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : null;

                if ($s3Path && Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                }
            }

            foreach ($product->images as $image) {
                $parsedUrl = parse_url($image->image_path);
                $s3Path = ltrim($parsedUrl['path'], '/');

                if (Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                }
            }

            // $product->images()->delete();
            $product->delete();

            if ($user->fcm_token) {
                $notifier->sendNotification('Product Deleted', 'Your product "' . $product->title . '" has been  by the admin.', $user->fcm_token);
            }

            return response()->json([
                'message' => 'Product and its associated images deleted successfully.',
                'status'  => 200,
                'error'   => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the product.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
        }
    }
}
