<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use App\Models\ProductCategory;
use App\Models\Product;

class ProductCategoryController extends BaseController
{

    public function addProductCategory(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name'        => 'required|string|max:255',
                'category_id' => 'nullable|exists:product_categories,id',
                'category_image'      => 'required|mimes:jpg,jpeg,png|max:2048', // Max file size is 2MB
                'category_type'   => 'nullable|in:sell,rent,both'
            ]);

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            if (!empty($request->name) && $request->has('category_id') && ($request->category_id === null || $request->category_id === '')) {
                return $this->sendError('Error.', 'Please select category.');
            }

            $category     = new ProductCategory();
            $category->category_name         = $request->name;
            $category->parent_id             = $request->category_id ?? 0;
            $category->category_type         = $request->category_type;

            if ($request->hasFile('category_image')) {
                $category->category_image = $this->uploadFile($request->file('category_image'), 'uploads/category_images');
            }
            $category->save();

            if ($request->filled('category_id')) {
                return $this->sendResponse($category, 'Sub Category Added Successfully');
            } else {
                return $this->sendResponse($category, 'Parent Category Added Successfully');
            }
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    // private function uploadFile($file, $directory)
    // {
    //     try {
    //         $fileName = time() . '_' . $file->getClientOriginalName();
    //         $file->move(public_path($directory), $fileName);
    //         return $directory . '/' . $fileName;
    //     } catch (\Exception $e) {
    //         throw new \Exception('File upload failed: ' . $e->getMessage());
    //     }
    // }

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

    public function updateProductCategory(Request $request, $id)
    {
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'name'       => 'required|string|max:255',
                'category_type'   => 'nullable|in:sell,rent,both',
                // 'category_id' => 'required_without:name|exists:product_categories,id',
                'category_id' => 'nullable|exists:product_categories,id',
                'category_image'      => 'nullable|mimes:jpg,jpeg,png|max:2048', // Max file size is 2MB
            ]);

            if ($validator->fails()) {
                throw new \Exception($validator->errors()->first());
            }

            if (!empty($request->name) && $request->has('category_id') && ($request->category_id === null || $request->category_id === '')) {
                return $this->sendError('Error.', 'Please select category.');
            }


            $category = ProductCategory::find($id);

            if (!$category) {
                return $this->sendError('Category not found', ['error' => 'Category not found to update'], 404);
            }

            $category->category_name  = $request->name;
            $category->category_type  = $request->category_type ?? $category->category_type;
            $category->parent_id = $request->category_id ?? 0;


            if ($request->hasFile('category_image')) {

                if (!empty($category->category_image)) {
                    $parsedUrl = parse_url($category->category_image);
                    $s3Path = ltrim($parsedUrl['path'], '/');

                    if (Storage::disk('s3')->exists($s3Path)) {
                        Storage::disk('s3')->delete($s3Path);
                    }
                }

                $category->category_image = $this->uploadFile($request->file('category_image'), 'uploads/category_images');
            }

            $category->save();

            if ($category->parent_id) {
                return $this->sendResponse($category, 'Sub Category Updated Successfully');
            } else {
                return $this->sendResponse($category, 'Main Category Updated Successfully');
            }
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }

    public function getProductCategory()
    {
        $baseUrl = url('public/');

        $categories = ProductCategory::with('subcategories')
            ->where('parent_id', 0)
            ->get();

        // $categories->transform(function ($category) use ($baseUrl) {
        //     if (!empty($category->category_image)) {
        //         $category->category_image = $baseUrl . '/' . ltrim($category->category_image, '/');
        //     }

        //     $category->subcategories->transform(function ($subcategory) use ($baseUrl) {
        //         if (!empty($subcategory->category_image)) {
        //             $subcategory->category_image = $baseUrl . '/' . ltrim($subcategory->category_image, '/');
        //         }
        //         return $subcategory;
        //     });

        //     return $category;
        // });

        return $this->sendResponse($categories, 'Main Category Get Successfully');
    }


    public function getProductSubCategory()
    {
        $sub_categories = ProductCategory::where('parent_id', '!=', 0)
            ->with('parent')
            ->get();

        $baseUrl = url('public/');
        // $sub_categories->transform(function ($sub_categories) use ($baseUrl) {
        //     if (!empty($sub_categories->category_image)) {
        //         $sub_categories->category_image = $baseUrl . '/' . ltrim($sub_categories->category_image, '/');
        //     }
        //     if (
        //         !empty($sub_categories->parent) && !empty($sub_categories->parent->category_image)
        //         && !str_starts_with($sub_categories->parent->category_image, 'http')
        //     ) {
        //         $sub_categories->parent->category_image = $baseUrl . '/' . ltrim($sub_categories->parent->category_image, '/');
        //     }
        //     return $sub_categories;
        // });
        return $this->sendResponse($sub_categories, 'Sub Category Get Successfully');
    }

    public function getSingleCategoryDetail($id)
    {

        $categories = ProductCategory::find($id);

        $baseUrl = url('public/');
        // if (!empty($categories->category_image)) {
        //     $categories->category_image = $baseUrl . '/' . ltrim($categories->category_image, '/');
        // }

        if (!$categories) {
            return $this->sendError('Category Not Found', ['error' => 'Not Found'], 404);
        }

        return $this->sendResponse($categories, 'Categories retrieved successfully.');
    }

    public function getSubCategory($id)
    {
        $category = ProductCategory::with('subCategories')->find($id);


        if (!$category) {
            return $this->sendError('Category Not Found', ['error' => 'Not Found'], 404);
        }

        $subCategories = $category->subCategories()->get();

        $baseUrl = url('public/');
        // $subCategories->transform(function ($categories) use ($baseUrl) {
        //     if (!empty($categories->category_image)) {
        //         $categories->category_image = $baseUrl . '/' . ltrim($categories->category_image, '/');
        //     }
        //     return $categories;
        // });

        return $this->sendResponse($subCategories, 'Sub Categories retrieved successfully.');
    }

    public function productCategoryDelete(Request $request, $id)
    {
        try {
            // $user = Auth::guard('api')->user();
            $ProductCategory = ProductCategory::find($id);

            if (!$ProductCategory) {
                return $this->sendError('Category not found', ['error' => 'Category not found'], 404);
            }

            // Get all related category IDs (parent + subcategories)
            $categoryIds = ProductCategory::where('parent_id', $id)->pluck('id')->toArray();
            $categoryIds[] = $id;

            // ✅ Delete all products under those categories
            $products = Product::with('images')->whereIn('category_id', $categoryIds)->get();

            foreach ($products as $product) {
                // Delete main product image if not default
                $defaultSoldImageUrl = 'https://sna-img-prod.s3.amazonaws.com/uploads/default/sold_out.png';

                if (!empty($product->product_image) && $product->product_image !== $defaultSoldImageUrl) {
                    $parsedUrl = parse_url($product->product_image);
                    $s3Path = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : null;

                    if ($s3Path && Storage::disk('s3')->exists($s3Path)) {
                        Storage::disk('s3')->delete($s3Path);
                    }
                }

                // Delete additional images from S3 and DB
                foreach ($product->images as $image) {
                    $parsedUrl = parse_url($image->image_path);
                    $s3Path = ltrim($parsedUrl['path'], '/');

                    if (Storage::disk('s3')->exists($s3Path)) {
                        Storage::disk('s3')->delete($s3Path);
                    }

                    $image->delete(); // delete image record
                }

                $product->delete(); // delete product
            }


            //  Delete subcategories and their images
            $subcategories = ProductCategory::where('parent_id', $id)->get();
            foreach ($subcategories as $subcategory) {
                if (!empty($subcategory->category_image)) {
                    $parsedUrl = parse_url($subcategory->category_image);
                    $s3Path = ltrim($parsedUrl['path'], '/');
                    if (Storage::disk('s3')->exists($s3Path)) {
                        Storage::disk('s3')->delete($s3Path);
                    }
                }
                $subcategory->delete();
            }

            // Delete main category image from S3
            if (!empty($ProductCategory->category_image)) {
                $parsedUrl = parse_url($ProductCategory->category_image);
                $s3Path = ltrim($parsedUrl['path'], '/');

                if (Storage::disk('s3')->exists($s3Path)) {
                    Storage::disk('s3')->delete($s3Path);
                }
            }

            $ProductCategory->delete();
            return $this->sendResponse('Delete', 'Category deleted successfully');
        } catch (\Exception $e) {
            return $this->sendError('Error.', $e->getMessage());
        }
    }
}
