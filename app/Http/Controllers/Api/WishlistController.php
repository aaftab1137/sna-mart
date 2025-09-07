<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Wishlist;
use App\Models\Product;
use Validator;

class WishlistController extends BaseController
{
    // All Wishlist
    public function getWishlist(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            $wishlist = Wishlist::where('user_id', $user->id)
                ->with([
                    'product',
                    'product.images:id,product_id,image_path',
                    'product.users:id,name,email'
                ])
                ->orderBy('id', 'desc')
                ->get();

            $baseUrl = url('public/');
            // $wishlist->transform(function ($product) use ($baseUrl) {
            //     if (!empty($product->product->product_image)) {
            //         $product->product->product_image = $baseUrl . '/' . $product->product->product_image;
            //     }

            //     if ($product->product && $product->product->images) {
            //         $product->product->images->transform(function ($image) use ($baseUrl) {
            //             $image->image_path = $baseUrl . '/' . $image->image_path;
            //             return $image;
            //         });
            //     }
            //     return $product;
            // });

            return response()->json([
                'message' => 'Wishlist retrieved successfully.',
                'status'  => 200,
                'error'   => false,
                'data'    => $wishlist,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while retrieving the wishlist.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
        }
    }

    // Add Wishlist
    public function addWishlist(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'status'  => 400,
                'error'   => true,
            ], 422);
        }

        $product = Product::find($request->product_id);
        if ($product->is_approve != 1) {
            return response()->json([
                'message' => 'This product is not available for wishlist',
                'status'  => 403,
                'error'   => true,
            ], 403);
        }

        $existingWishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $request->product_id)
            ->first();

        if ($existingWishlist) {
            return response()->json([
                'message' => 'Product is already in your wishlist',
                'status'  => 409,
                'error'   => true,
            ], 409);
        }

        $wishlist = new Wishlist();
        $wishlist->user_id = $user->id;
        $wishlist->product_id = $request->product_id;
        $wishlist->save();

        return response()->json([
            'message' => 'Product added to wishlist successfully',
            'status'  => 200,
            'error'   => false,
            'data'    => $wishlist,
        ]);
    }

    // Remove wishlist
    public function removeWishlist(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();

            $wishlistItem = Wishlist::where(['user_id' => $user->id, 'product_id' => $id])->first();

            if (!$wishlistItem) {
                return response()->json([
                    'message' => 'No wishlist item found for the provided ID.',
                    'status'  => 200,
                    'error'   => true,
                ], 200);
            }

            $wishlistItem->delete();

            return response()->json([
                'message' => 'Wishlist item removed successfully.',
                'status'  => 200,
                'error'   => false,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the wishlist item.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
        }
    }
}
