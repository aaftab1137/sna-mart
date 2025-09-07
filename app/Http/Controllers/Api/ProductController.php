<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductView;
use App\Models\SponsoredProduct;
use App\Models\UserMembership;
use App\Models\Wishlist;
use App\Models\User;
use App\Models\RecentSearch;
use Illuminate\Validation\Rule;

class ProductController extends BaseController
{

    public function createProduct(Request $request)
    {
        $user = Auth::guard('api')->user();

        $imageLimit = $user->is_plan_active == 1 ? $user->product_upload_limit : null;

        $rules = [
            'category_id'           => 'required|exists:product_categories,id',
            'product_type'          => 'sometimes|string|max:255',
            'sell_or_rent'          => 'required|in:sell,rent',
            'title'                 => 'required|string|max:255',
            'subtitle'              => 'nullable|string|max:255',
            'price'                 => 'required_if:sell_or_rent,sell|nullable|numeric',
            'lat'                   => 'nullable|numeric',
            'long'                  => 'nullable|numeric',
            'rent_prices'           => 'required_if:sell_or_rent,rent|array',
            'rent_prices.hourly'    => 'nullable|numeric',
            'rent_prices.daily'      => 'nullable|numeric',
            'rent_prices.month'      => 'nullable|numeric',
            //'available_sizes'       => 'required|array',  // Ensure array exists
            'available_sizes.*'     => 'required|string',  // Ensure each value in array is not empty
            //'available_colors'      => 'required|array',
            'available_colors.*'    => 'required|string',
            'additional_description' => 'nullable|string',
            'location'               => 'required|string',
            'product_image'          => 'required|mimes:jpeg,png,jpg,webp|max:5120',
            'images'   => 'nullable|array|max:5',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048', // Validate each image
        ];

        // Apply max image count only if user has active plan
        $totalUploadedImages = 0;
        if ($request->hasFile('product_image')) $totalUploadedImages += 1;
        if ($request->hasFile('images')) $totalUploadedImages += count($request->file('images'));

        // Check total against user limit
        if ($imageLimit !== null && $totalUploadedImages > $imageLimit) {
            return response()->json([
                'status' => 400,
                'message' => 'You can upload a maximum of ' . $imageLimit . ' image(s) as per your current plan.',
            ], 400);
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => 400, 'message' => $validator->errors()->first()], 400);
        }

        $product = new Product();
        $product->user_id           = $user->id;
        $product->product_type       = $request->product_type;
        $product->category_id       = $request->category_id;
        $product->sell_or_rent      = $request->sell_or_rent;
        $product->title             = $request->title;
        $product->subtitle          = $request->subtitle;
        $product->price             = $request->price;
        $product->available_sizes   = $request->available_sizes;
        $product->available_colors  = $request->available_colors;
        $product->additional_description = $request->additional_description;
        $product->location              = $request->location;
        $product->lat                 = $request->lat;
        $product->long                 = $request->long;
        // Handle rent prices
        if ($request->sell_or_rent == 'rent') {
            $product->rent_price_per_hour = $request->input('rent_prices.hourly');
            $product->rent_price_per_day  = $request->input('rent_prices.daily');
            $product->rent_price_per_month  = $request->input('rent_prices.month');
        }


        if ($request->hasFile('product_image')) {
            $directory = 'uploads/product_images';
            $product->product_image = $this->uploadFile($request->file('product_image'), $directory);
        }

        $product->save();

        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $imagePath = $this->uploadFile($image, 'uploads/product_images');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => $imagePath,
                ]);
            }
        }

        return response()->json(['status' => 200, 'message' => 'Product added successfully!', 'product' => $product]);
    }

    // Product Update
    public function updateProduct(Request $request, $productId)
    {
        $user = Auth::guard('api')->user();

        $imageLimit = $user->is_plan_active == 1 ? $user->product_upload_limit : null;
        try {
            // Validate input
            $validator = Validator::make($request->all(), [
                'category_id'               => 'required|exists:product_categories,id',
                'product_type'              => 'sometimes|string|max:255',
                'sell_or_rent'              => 'required|in:sell,rent',
                'title'                     => 'required|string|max:255',
                'subtitle'                  => 'nullable|string|max:255',
                'price'                     => 'required_if:sell_or_rent,sell|nullable|numeric',
                'available_sizes.*'          => 'required',
                'rent_prices'           => 'required_if:sell_or_rent,rent|array',
                'rent_prices.hourly'    => 'nullable|numeric',
                'rent_prices.daily'      => 'nullable|numeric',
                'rent_prices.month'      => 'nullable|numeric',
                'available_colors.*'         => 'required',
                'additional_description'    => 'nullable|string',
                'location'                  => 'nullable',
                'product_image'          => 'nullable|mimes:jpeg,png,jpg,webp|max:4096',
                'lat'                   => 'nullable|numeric',
                'long'                  => 'nullable|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors()->first(),
                    'status'  => 400,
                    'error'   => true,
                ], 400);
            }

            $totalUploadedImages = 0;
            if ($request->hasFile('product_image')) $totalUploadedImages += 1;
            if ($request->hasFile('images')) $totalUploadedImages += count($request->file('images'));

            // Check total against user limit
            if ($imageLimit !== null && $totalUploadedImages > $imageLimit) {
                return response()->json([
                    'status' => 400,
                    'message' => 'You can upload a maximum of ' . $imageLimit . ' image(s) as per your current plan.',
                ], 400);
            }

            // Find the product
            $product = Product::find($productId);

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found.',
                    'status'  => 404,
                    'error'   => true,
                ], 404);
            }

            // Update fields if provided
            $product->category_id             = $request->category_id ?? $product->category_id;
            $product->product_type             = $request->product_type ?? $product->product_type;
            $product->title                   = $request->title ?? $product->title;
            $product->additional_description  = $request->additional_description ?? $product->additional_description;
            $product->sell_or_rent            = $request->sell_or_rent ?? $product->sell_or_rent;
            $product->price                   = $request->price ?? $product->price;
            $product->subtitle                = $request->subtitle ?? $product->subtitle;
            $product->available_sizes          = $request->available_sizes ?? $product->available_sizes;
            $product->available_colors         = $request->available_colors ?? $product->available_colors;
            $product->location                = $request->location ?? $product->location;
            $product->lat                     = $request->lat ?? $product->lat;
            $product->long                     = $request->long ?? $product->long;

            if ($product->sell_or_rent === 'rent' && $request->has('rent_prices')) {
                $product->rent_price_per_hour = $request->input('rent_prices.hourly') ?? $product->rent_price_per_hour;
                $product->rent_price_per_day  = $request->input('rent_prices.daily') ?? $product->rent_price_per_day;
                $product->rent_price_per_month  = $request->input('rent_prices.month') ?? $product->rent_price_per_month;
            }

            if ($request->hasFile('product_image')) {
                // Delete the old image if it exists
                // $oldImagePath = public_path($product->product_image);
                // if (!empty($product->product_image) && file_exists($oldImagePath)) {
                //     unlink($oldImagePath);
                // }
                $oldImagePath = public_path('uploads/product_images/' . basename($product->product_image));
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }

                // Upload and store the new image
                $product->product_image = $this->uploadFile($request->file('product_image'), 'uploads/product_images');
            }

            $product->save();

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $imagePath = $this->uploadFile($image, 'uploads/product_images');
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                    ]);
                }
            }

            return response()->json([
                'message' => 'Product updated successfully.',
                'status'  => 200,
                'error'   => false,
                'data'    => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while updating the product.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
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

    // All product
    public function getProduct(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            $userLat = null;
            $userLon = null;

            if ($user) {
                $UserDetail = User::find($user->id);
                if ($UserDetail && $UserDetail->lat && $UserDetail->long) {
                    $userLat = $UserDetail->lat;
                    $userLon = $UserDetail->long;
                }
            }

            $query = Product::withCount('views')->orderBy('id', 'desc');

            if (!$user || $user->user_type !== 'admin') {
                $query->where('is_approve', true)
                    ->where('is_sold', false);

                if ($user) {
                    $query->where('user_id', '!=', $user->id);
                }
                // ->where('user_id', '!=', $user->id);
            }

            if ($user && $user->user_type === 'admin' && $request->has('is_approve')) {
                $query->where('is_approve', $request->is_approve);
            }

            if ($request->has('sell_or_rent')) {
                $query->where('sell_or_rent', $request->sell_or_rent);
            }

            if ($request->has('is_sold')) {
                $query->where('is_sold', $request->is_sold);
            }

            if ($request->has('product_type')) {
                $query->where('product_type', $request->product_type);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('category_name', 'like', '%' . $search . '%')
                                ->orWhereHas('parent', function ($parentQuery) use ($search) {
                                    $parentQuery->where('category_name', 'like', '%' . $search . '%');
                                });
                        });
                });
            }

            $query->with([
                'images:id,product_id,image_path',
                'category:id,category_name,parent_id',
                'category.parent:id,category_name',
                'category.subcategories:id,category_name,parent_id'
            ]);

            // Fetch all products first
            $products = $query->get();

            // If user has location, calculate distance
            if ($userLat && $userLon) {
                $products = $products->map(function ($product) use ($userLat, $userLon) {
                    $distance = $this->calculateDistance($userLat, $userLon, $product->lat, $product->long);
                    $product->distance = number_format((float) $distance, 2, '.', '') . ' km';
                    return $product;
                })->sortBy('distance')->values();
            }

            // ✅ Convert paginate param to boolean
            $paginate = filter_var($request->query('paginate', 'false'), FILTER_VALIDATE_BOOLEAN);

            if ($paginate) {
                $page = $request->get('page', 1);
                $perPage = 10;

                $products = new LengthAwarePaginator(
                    $products->forPage($page, $perPage)->values(),
                    $products->count(),
                    $perPage,
                    $page,
                    ['path' => url()->current(), 'query' => $request->query()]
                );
            }

            // Add wishlist flag
            $products->transform(function ($product) use ($user) {
                $product->is_wishlist = $user ? Wishlist::where([
                    'product_id' => $product->id,
                    'user_id' => $user->id
                ])->exists() : false;

                return $product;
            });

            return $this->sendResponse($products, 'All Products');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    // get sell/rent product
    public function getSellOrRentProduct(Request $request)
    {
        try {

            $sell_or_rent = $request->query('sell_or_rent', 'sell');

            $Product = Product::query();

            if ($request->filled('search')) {
                $Product->where('title', 'like', '%' . $request->search . '%');
            }

            $Product->with([
                'images:id,product_id,image_path',
                'category:id,category_name,parent_id',
                'category.parent:id,category_name',
                'category.subcategories:id,category_name,parent_id'
            ])->where('sell_or_rent', $sell_or_rent)->where('is_approve', '=', 1)->where('is_sold', 0);

            $baseUrl = url('public/');

            if ($request->has('paginate') && $request->paginate == 'false') {
                $get_products = $Product->get();
            } else {
                $get_products = $Product->OrderBy('id', 'desc')->paginate(10);
                $get_products->getCollection()->transform(function ($product) use ($baseUrl) {

                    return $product;
                });
            }

            return $this->sendResponse($get_products, 'All Products');
        } catch (\Exception $e) {
            \Log::error('Product Fetch Error: ' . $e->getMessage());

            return $this->sendError('Error fetching products.', $e->getMessage());
        }
    }

    // product details
    public function productDetails(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();
            // Fetch product with relations
            $product = Product::with([
                'images:id,product_id,image_path',
                'category:id,category_name,parent_id',
                'category.parent:id,category_name',
                'category.subcategories:id,category_name,parent_id',
                'users:id,name,email',

            ])->withCount('views')->find($id);

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found.',
                    'status'  => 404,
                    'error'   => true,
                ], 404);
            }

            $baseUrl = rtrim(url('public/'), '/');

            ProductView::firstOrCreate([
                'product_id' => $product->id,
                'user_id'     =>  $user->id,
            ]);

            return response()->json([
                'message'        => 'Product details retrieved successfully.',
                'status'         => 200,
                'error'          => false,
                'data'           => $product,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred.',
                'status'  => 500,
                'error'   => true,
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    // Delete Product
    public function productDelete(Request $request, $id)
    {
        try {
            $user = Auth::guard('api')->user();

            $product = Product::where(['user_id' => $user->id])->find($id);

            if (!$product) {
                return response()->json([
                    'message' => 'Product not found.',
                    'status'  => 404,
                    'error'   => true,
                ], 404);
            }

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

    //delete product images 
    public function productImageDelete(Request $request, $id)
    {
        try {
            $productImage = ProductImage::find($id);

            if (!$productImage) {
                return response()->json([
                    'message' => 'Product image not found.',
                    'status'  => 404,
                    'error'   => true,
                ], 404);
            }

            // Delete image from S3
            if (!empty($productImage->image_path)) {
                $parsedUrl = parse_url($productImage->image_path);
                $s3Path = ltrim($parsedUrl['path'], '/');

                if (Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                }
            }

            $productImage->delete();

            return response()->json([
                'message' => 'Product image deleted successfully.',
                'status'  => 200,
                'error'   => false,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred while deleting the product image.',
                'status'  => 500,
                'error'   => true,
                'data'    => $e->getMessage(),
            ], 500);
        }
    }

    // MY Product
    public function getMyProduct(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (!$user) {
                return $this->sendError('Unauthorized.', [], 401);
            }

            $Product = Product::query()->withCount('views');

            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $Product->where(function ($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                        ->orWhereHas('category', function ($categoryQuery) use ($search) {
                            $categoryQuery->where('category_name', 'like', '%' . $search . '%')
                                ->orWhereHas('parent', function ($parentQuery) use ($search) {
                                    $parentQuery->where('category_name', 'like', '%' . $search . '%');
                                });
                        });
                });
            }


            $Product->with([
                'images:id,product_id,image_path',
                'category:id,category_name,parent_id',
                'category.parent:id,category_name',
                'category.subcategories:id,category_name,parent_id',
                'users:id,name',
            ])->where('user_id', $user->id);

            $baseUrl = url('public/');

            if ($request->has('paginate') && $request->paginate == 'true') {
                $get_products = $Product->paginate(10);
            } else {
                $get_products = $Product->OrderBy('id', 'desc')->get();
            }
            $get_products->transform(function ($product) use ($baseUrl, $user) {

                $product->is_wishlist = $user ? Wishlist::where(['product_id' => $product->id, 'user_id' => $user->id])->exists() : false;

                return $product;
            });

            return $this->sendResponse($get_products, 'All Products');
        } catch (\Exception $e) {
            \Log::error('Product Fetch Error: ' . $e->getMessage());

            return $this->sendError('Error fetching products.', $e->getMessage());
        }
    }

    // public function getMostViewedProducts()
    // {
    //     $user = Auth::guard('api')->user();

    //     $userLat = null;
    //     $userLon = null;

    //     if ($user && $user->lat && $user->long) {
    //         $userLat = $user->lat;
    //         $userLon = $user->long;
    //     }

    //     $products = Product::with([
    //         'images:id,product_id,image_path',
    //         'users:id,name,email',
    //         'category:id,category_name,category_image'
    //     ])
    //         ->when($user, function ($query) use ($user) {
    //             return $query->where('user_id', '!=', $user->id);
    //         })
    //         ->where('is_approve', 1)
    //         ->where('is_sold', 0)
    //         ->orderByDesc('id')
    //         ->take(10)
    //         ->get();

    //     if ($userLat && $userLon) {
    //         $products = $products->map(function ($product) use ($userLat, $userLon) {
    //             $distance = $this->calculateDistance($userLat, $userLon, $product->lat, $product->long);
    //             $product->distance = round($distance, 2) . ' km';
    //             return $product;
    //         })->sortBy('distance')->values();
    //     }

    //     return response()->json([
    //         'status' => 200,
    //         'products' => $products
    //     ]);
    // }
public function getMostViewedProducts()
{
    $user = Auth::guard('api')->user();

    $userLat = null;
    $userLon = null;

    if ($user && $user->lat && $user->long) {
        $userLat = $user->lat;
        $userLon = $user->long;
    }

    $products = Product::with([
        'images:id,product_id,image_path',
        'users:id,name,email',
        'category:id,category_name,category_image'
    ])
        ->when($user, function ($query) use ($user) {
            return $query->where('user_id', '!=', $user->id);
        })
        ->where('is_approve', 1)
        ->where('is_sold', 0)
        ->get();

    // If location is available, sort by distance only
    if ($userLat && $userLon) {
        $products = $products->map(function ($product) use ($userLat, $userLon) {
            $distance = $this->calculateDistance($userLat, $userLon, $product->lat, $product->long);
            $product->distance_value = $distance;
            $product->distance = round($distance, 2) . ' km';
            return $product;
        })->sortBy('distance_value')->values();
    }

    // Take top 10 distance-wise closest products
    $products = $products->take(50);

    return response()->json([
        'status' => 200,
        'products' => $products
    ]);
}

    public function filterProducts(Request $request)
    {
        $user = Auth::guard('api')->user();


        $userLat = null;
        $userLon = null;

        // Step 1: Filter based on category & product_type
        // $query = Product::query();
        $query = Product::where('is_approve', '=', 1)->where('is_sold', 0);

        // If user is authenticated and has location
        if ($user) {
            $query->where('user_id', '!=', $user->id);
            $UserDetail = User::where('id', $user->id)->first();
            if ($UserDetail && $UserDetail->lat && $UserDetail->long) {
                $userLat = $UserDetail->lat;
                $userLon = $UserDetail->long;
            }
        }



        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('product_type')) {
            $query->where('product_type', $request->product_type);
        }

        $filteredProducts = $query->with(['category', 'images'])->get();

        if ($userLat && $userLon) {
            foreach ($filteredProducts as $product) {
                $distance = $this->calculateDistance($userLat, $userLon, $product->lat, $product->long);
                $product->distance = $distance . ' km';
            }

            // Step 3: Sort by distance (nearby first)
            $filteredProducts = $filteredProducts->sortBy('distance')->values();
        }

        return response()->json(['status' => 200, 'products' => $filteredProducts]);
    }

    //sponsored product
    public function sponsorProduct(Request $request, $productId)
    {
        $user = Auth::guard('api')->user();

        $product = Product::where('id', $productId)->where('user_id', $user->id)->first();

        if (!$product) {
            return response()->json([
                'status'    => 404,
                'message'   => 'product is not found or you are not authorized',
            ], 404);
        }

        $membership = UserMembership::where('user_id', $user->id)->where('expires_at', '>', now())->first();

        if (!$membership) {
            return response()->json(['status' => 403, 'message' => 'Only members can sponserd plan'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'  => 'required|string',
            'days'   => 'required|integer|min:1|max:30',
            'resell_price'  => 'numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['status'  => 400, 'message' => $validator->errors()->first()], 400);
        }

        $existingSponsorship = SponsoredProduct::where('product_id', $product->id)->where('user_id', $user->id)->first();

        if ($existingSponsorship) {
            return response()->json([
                'status' => 400,
                'message' => 'You have already sponsored this product'
            ], 400);
        }

        $days = (int) $request->input('days');

        $sponsoredProduct = SponsoredProduct::create([
            'user_id'          => $user->id,
            'product_id'       => $product->id,
            'title'            => $request->title,
            'resell_price'     => $request->resell_price,
            'sponsored_untill' => now()->addDays($days),
        ]);

        // Update product sponsorship status
        $product->is_sponsored = true;
        $product->sponsored_untill = $sponsoredProduct->sponsored_untill;

        $product->save();

        return response()->json([
            'status'    => 200,
            'message'   => 'Product sponsored successfully!',
            'product'   => $sponsoredProduct,
        ]);
    }

    public function getSponsoredProduct()
    {

        $sponsoredProduct = SponsoredProduct::with(['product', 'user:id,name,email', 'product.images:id,product_id,image_path'])->get();

        $baseUrl = url('public/');

        return $this->sendResponse($sponsoredProduct, 'All Sponsored Products Retrived Successfully');
    }

    //get my sponsored product
    public function getMySponsoredProduct()
    {
        $user = Auth::guard('api')->user();

        $sponsoredProduct = SponsoredProduct::where('user_id', $user->id)->with(['product', 'user:id,name,email', 'product.images:id,product_id,image_path'])->get();

        $baseUrl = url('public/');

        $sponsoredProduct->transform(function ($product) use ($baseUrl) {

            if ($product->product->product_image) {
                $product->product->product_image = $baseUrl . '/' . $product->product->product_image;
            }

            return $product;
        });

        return $this->sendResponse($sponsoredProduct, 'My Sponsored Products Retrived Successfully');
    }

    public function removeSponsorship($productId)
    {
        $user = Auth::guard('api')->user();

        $product = Product::where('id', $productId)->where('user_id', $user->id)->first();

        if (!$product) {
            return response()->json([
                'status'  => 404,
                'message' => 'Product not found or you are not authorized',
            ], 404);
        }


        $sponsoredProduct = SponsoredProduct::where('product_id', $productId)->where('user_id', $user->id)->first();

        if (!$sponsoredProduct) {
            return response()->json([
                'status'  => 400,
                'message' => 'This product is not currently sponsored or you are not authorized',
            ], 400);
        }


        $sponsoredProduct->delete();

        $product->is_sponsored = false;
        $product->sponsored_untill = null;
        $product->save();

        return response()->json([
            'status'  => 200,
            'message' => 'Sponsorship removed successfully!',
            // 'product' => $product,
        ]);
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
        return round($distance, 2); // in kilometers
    }

    public function markAsSold(Request $request, $id)
    {
        $user = Auth::guard('api')->user();
        $product = Product::where('id', $id)->where('user_id', $user->id)->first();

        if (!$product) {
            return response()->json([
                'status' => 404,
                'message' => 'Product not found.',
            ], 404);
        }

        // Delete product main image from S3 only if it's not the default sold-out image
        $defaultSoldImagePath = '/uploads/default/sold_out.png';
        if (!empty($product->product_image)) {
            $parsedUrl = parse_url($product->product_image);
            $s3Path = ltrim($parsedUrl['path'], '/');

            if ($s3Path !== ltrim($defaultSoldImagePath, '/') && Storage::disk('s3')->exists($s3Path)) {
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

        $soldImageUrl = 'https://sna-img-prod.s3.amazonaws.com/uploads/default/sold_out.png';
        // $soldImageUrl = rtrim(env('AWS_URL'), '/') . '/uploads/default/sold_out.png';
        $product->is_sold = true;
        $product->product_image = $soldImageUrl;
        $product->save();


        return response()->json([
            'status' => 200,
            'message' => 'Product marked as sold out successfully.',
            'product' => $product
        ]);
    }

    public function storeRecentSearch(Request $request)
    {
        $user = Auth::guard('api')->user();

        $validator = Validator::make($request->all(), [
            'search_term' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'message' => $validator->errors()->first()], 422);
        }

        // Check if same search_term exists → update timestamp instead of duplicate
        $existing = RecentSearch::where('user_id', $user->id)
            ->where('search_term', $request->search_term)
            ->first();

        if ($existing) {
            $existing->touch(); // update `updated_at` to now
        } else {
            RecentSearch::create([
                'user_id'     => $user->id,
                'search_term' => $request->search_term
            ]);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Search term stored successfully.',
        ]);
    }

    public function getRecentSearches()
    {
        $user = Auth::guard('api')->user();

        $recentSearches = RecentSearch::where('user_id', $user->id)
            ->latest()
            ->take(10)
            ->pluck('search_term');

        return response()->json(['status' => 200, 'data' => $recentSearches]);
    }
}