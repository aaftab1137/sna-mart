<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BoostingPlan;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\BoostingProduct;
use Carbon\Carbon;
use Validator;
use Barryvdh\DomPDF\Facade\Pdf;

class BoostingProductController extends Controller
{
    
public function boostProduct(Request $request, $productId)
{

    $user = Auth::guard('api')->user();

    $product = Product::where('id', $productId)->where('user_id', $user->id)->first();

    if (!$product) {
        return response()->json(['status' => 404, 'message' => 'Product not found or unauthorized'], 404);
    }

    $validator = Validator::make($request->all(), [
        'boosting_plan_id' => 'nullable|exists:boosting_plans,id',
        'days'              => 'nullable|integer|min:1'
    ]);

    if ($validator->fails()) {
        return response()->json(['status'  => 404, 'message' => $validator->errors()->first()], 404);
    }

    $plan = BoostingPlan::find($request->boosting_plan_id);

    $existingBoost = BoostingProduct::where('product_id', $productId)->where('user_id', $user->id)
        ->where('boosted_until', '>', now()) // Active boosted product
        ->first();

    $remainingDays = 0;

    if ($existingBoost) {
        $remainingDays = now()->diffInDays($existingBoost->boosted_until);
        $message = 'Product boosted detail updated successfully!';
    } else {
        $message = 'Product boosted successfully!';
    }

    if ($plan) {
        $totalDays = $request->days + $remainingDays;
        $boostedUntil = now()->addDays($totalDays);
        $total_price  = $plan->price * ($request->days ?? 0);
    } else {
        // Free boosting
        $totalDays = null;
        $boostedUntil = null;
        $total_price = null;
    }

    $boostedProduct = BoostingProduct::updateOrCreate(
        [
            'user_id'    => $user->id,
            'product_id' => $product->id
        ],
        [
            'user_id'         => $user->id,
            'boosting_plan_id' => $plan->id ?? null,
            'days'             => $totalDays,
            'total_price'      => $total_price,
            'boosted_until'    => $boostedUntil,
        ]
    );
    return response()->json([
        'status' => 200,
        'message' => $message,
        'boosted_until' => $boostedProduct
    ]);
}


// public function getBoostedProducts()
// {
//     $user = Auth::guard('api')->user();

//     if (!$user) {
//         return response()->json(['status' => 401, 'message' => 'Unauthorized: User not logged in']);
//     }

//     $UserDetail = User::find($user->id);

//     if (!$UserDetail || !$UserDetail->lat || !$UserDetail->long) {
//         return response()->json(['status' => 400, 'message' => 'User location not found']);
//     }

//     $userLat = $UserDetail->lat;
//     $userLon = $UserDetail->long;

//     $boostedProducts = BoostingProduct::with(['product', 'user:id,name,email', 'boostingPlan'])
//         ->whereHas('product', function ($query) use ($user) {
//             $query->where('is_approve', 1)->where('is_sold', 0);

//             if ($user) {
//                 $query->where('user_id', '!=', $user->id);
//             }
//         })
//         ->get();

//     foreach ($boostedProducts as $product) {
//         $distance = $this->calculateDistance($userLat, $userLon, $product->product->lat, $product->product->long);
//         $product->distance_value = $distance;
//         $product->distance = round($distance, 2) . ' km';
//     }

//     // Sort by distance
//     $sortedProducts = $boostedProducts->sortBy('distance_value')->values();

//     return response()->json(['status' => 200, 'boosted_products' => $sortedProducts]);
// }

public function getBoostedProducts()
{
    $user = Auth::guard('api')->user();

    $userLat = null;
    $userLon = null;

    // Get user's location if logged in and location is set
    if ($user) {
        $UserDetail = User::find($user->id);

        if ($UserDetail && $UserDetail->lat && $UserDetail->long) {
            $userLat = $UserDetail->lat;
            $userLon = $UserDetail->long;
        }
    }

    // Fetch boosted products
    $boostedProducts = BoostingProduct::with(['product', 'user:id,name,email', 'boostingPlan'])
        ->whereHas('product', function ($query) use ($user) {
            $query->where('is_approve', 1)->where('is_sold', 0);

            if ($user) {
                // Exclude own products
                $query->where('user_id', '!=', $user->id);
            }
        })
        ->get();

    // Add distance if location is available
    if ($userLat && $userLon) {
        foreach ($boostedProducts as $product) {
            if ($product->product && $product->product->lat && $product->product->long) {
                $distance = $this->calculateDistance($userLat, $userLon, $product->product->lat, $product->product->long);
                $product->distance_value = $distance;
                $product->distance = round($distance, 2) . ' km';
            } else {
                $product->distance_value = null;
                $product->distance = null;
            }
        }

        // Sort by distance if available
        $boostedProducts = $boostedProducts->sortBy('distance_value')->values();
    }

    return response()->json(['status' => 200, 'boosted_products' => $boostedProducts]);
}

//remove 
public function removeBoost($productId)
{
    $user = Auth::guard('api')->user();

    $boostedProduct = BoostingProduct::where('product_id', $productId)->where('user_id', $user->id)->first();

    if (!$boostedProduct) {
        return response()->json(['status' => 404, 'message' => 'Boosted product not found'], 404);
    }

    $boostedProduct->delete();

    return response()->json(['status' => 200, 'message' => 'Boost removed successfully']);
}

// get my boosted product
public function getMyBoostedProducts()
{

    $user = Auth::guard('api')->user();

    $boostedProducts = BoostingProduct::where('user_id', $user->id)
        ->select('id', 'product_id', 'boosting_plan_id', 'boosted_until') // Include user_id
        ->with(['product:id,title,product_image,price,location', 'boostingPlan'])
        ->get();


    $baseUrl = url('public/');
    $boostedProducts->transform(function ($product) use ($baseUrl) {

        if ($product->product->product_image) {
            $product->product->product_image = $baseUrl . '/' . $product->product->product_image;
        }


        return $product;
    });

    return response()->json(['status' => 200, 'data' => $boostedProducts]);
}

public function downloadBoostedProductInvoice(Request $request, $boostingId)
{

    $user = Auth::guard('api')->user();

    $boosting = BoostingProduct::where('id', $boostingId)->where('user_id', $user->id)
        ->with(['product', 'boostingPlan'])->first();

    if (!$boosting) {
        return response()->json(['status' => 404, 'message' => 'This boosting record does not exist'], 404);
    }

    $purchaseDate = Carbon::parse($boosting->created_at)->format('d-m-y');
    $expiryDate   = Carbon::parse($boosting->boosted_until)->format('d-m-y');

    $data = [
        'user'          => $user,
        'boosting'      => $boosting,
        'product'       => $boosting->product,
        'plan'          => $boosting->boostingPlan,
        'purchase_date' => $purchaseDate,
        'expiry_date'   => $expiryDate,
        'total_price'   => $boosting->total_price,
        'days'          => $boosting->days,
    ];

    $pdf = Pdf::loadView('invoices.boostproduct_invoice', $data);

    return $pdf->download('boosting_invoice.pdf');
}

// get my boosted product
public function productBoosting(Request $request)
{

    $user = Auth::guard('api')->user();

    $productId = $request->query('product_id');
    $boostPlanId = $request->query('boosting_plan_id');

    $boostingPlan = BoostingPlan::where('id', $boostPlanId)->get();
    $boostedProducts = Product::where('id', $productId)->get();


    $baseUrl = url('public/');
    $boostedProducts->transform(function ($product) use ($baseUrl) {

        if ($product->product_image) {
            $product->product_image = $baseUrl . '/' . $product->product_image;
        }


        return $product;
    });

    return response()->json(
        [
            'status' => 200,
            'boostedProducts' => $boostedProducts,
            'boostingPlan'    => $boostingPlan
        ]
    );
}

private function calculateDistance($lat1, $lon1, $lat2, $lon2)
{
    $earthRadius = 6371; // Radius in KM

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat / 2) * sin($dLat / 2) +
        cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
        sin($dLon / 2) * sin($dLon / 2);

    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    $distance = $earthRadius * $c;
    // echo $distance;die('calculet');
    return round($distance, 2); // in kilometers
}
}
