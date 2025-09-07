<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\BaseController as BaseController;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use Validator;

class AdvertisementController extends BaseController
{

// add Advertisement
public function addAdvertisement(Request $request)
{
   //die('opopopop');
    $validator = Validator::make($request->all(), [

        'title' => 'required|string|unique:advertisements,title',
        'image'      => 'required|mimes:jpg,jpeg,png|max:2048', // Max file size is 2MB
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
            'status' => 400,
            'error' => true,
        ], 422);
    }
    try {
        $advertisement = new  Advertisement();
        $advertisement->title       = $request->title;

        // Handle file uploads and save the file paths
        if ($request->hasFile('image')) {
            $advertisement->image = $this->uploadFile($request->file('image'), 'uploads/advertisement_image');
        }

        $advertisement->save();
        return response()->json([
            'message' => 'Advertisement saved successfully',
            'status'  => 200,
            'error'   => false,
            'data'    => $advertisement,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'An error occurred while processing your request.',
            'status'  => 500,
            'error'   => true,
            'details' => $e->getMessage(),
        ], 500);
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
     
// delete Advertisement
// public function deleteAdvertisement($id)
// {
//     $advertisement = Advertisement::find($id);

//     if (!$advertisement) {
//         return response()->json([
//             'data' => [],
//             'message' => 'Advertisement Id not found',
//             'status' => 404,
//             'error' => true,
//         ], 404);
//     }

//     // Delete the image file from the server if it exists
//     $imagePath = public_path($advertisement->image);
//     if (!empty($advertisement->image) && file_exists($imagePath)) {
//         unlink($imagePath);
//     }
//     $advertisement->delete();

//     return response()->json([
//         'message' => 'Advertisement deleted successfully',
//         'status' => 200,
//         'error' => false,
//     ]);
// }

public function deleteAdvertisement($id)
{
    $advertisement = Advertisement::find($id);

    if (!$advertisement) {
        return response()->json([
            'message' => 'Advertisement Id not found',
            'status' => 404,
            'error' => true,
        ], 404);
    }

    // Delete the image from S3 if it exists
    if (!empty($advertisement->image)) {
        // Extract relative path from full URL
        $parsedUrl = parse_url($advertisement->image);
        $s3Path = ltrim($parsedUrl['path'], '/'); // remove leading slash

        if (Storage::disk('s3')->exists($s3Path)) {
            Storage::disk('s3')->delete($s3Path);
        }
    }

    $advertisement->delete();

    return response()->json([
        'message' => 'Advertisement deleted successfully',
        'status' => 200,
        'error' => false,
    ]);
}

//Update Advertisement
public function updateAdvertisement(Request $request, $id)
{
    // Validate the request
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|unique:advertisements,title,'. $request->id,
        'image'      => 'nullable|mimes:jpg,jpeg,png|max:2048', // Max file size is 2MB
        // 'image' => 'nullable|file|mimes:jpg,jpeg,png|max:2048',

    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => $validator->errors()->first(),
            'status' => 400,
            'error' => true,
        ], 422);
    }

    // Find the issue option by ID 
    $advertisement = Advertisement::find($id);

    if (!$advertisement) {
        return response()->json([
            'data' => [],
            'message' => 'Advertisement Id Not Found',
            'status' => 404,
            'error' => true,
        ], 404);
    }

    $advertisement->title = $request->title ?? $advertisement->title;
    if ($request->hasFile('image')) {
        // Delete the old image if it exists
        $oldImagePath = public_path($advertisement->image);
        if (!empty($advertisement->image) && file_exists($oldImagePath)) {
            unlink($oldImagePath);
        }

        // Upload and store the new image
        $advertisement->image = $this->uploadFile($request->file('image'), 'uploads/advertisement_image');
    }
    $advertisement->save();

    return response()->json([
        'data' => $advertisement,
        'message' => 'Advertisement Updated Successfully',
        'status' => 200,
        'error' => false,
    ], 200);
}

// Get AllAdvertisement
public function getAllAdvertisement(Request $request)
{
    try {
        // if ($request->has('paginate') && $request->paginate == 'false') {
        //     $advertisement = Advertisement::all();
        // } else {
        //     $advertisement = Advertisement::paginate(10);
        // }
        $advertisement = Advertisement::orderBy('id', 'desc')->get();

        $baseUrl = url('public/');
        $advertisement->transform(function ($advertisement) use ($baseUrl) {
            if (!empty($advertisement->image)) {
                $advertisement->image = ltrim($advertisement->image, '/');
            }
            return $advertisement;
        });
        return response()->json([
            'data' => $advertisement,
            'message' => 'Advertisement retrieved successfully',
            'status' => 200,
            'error' => false,
        ]);
    } catch (\Exception $e) {
        return $this->sendError('Error.', $e->getMessage());
    }
}

// get Advertisement by id 
public function getAdvertisementDetailById($Id)
{
    $advertisement = Advertisement::where('id', $Id)->get();

    // $baseUrl = url('public/');
    // $advertisement->transform(function ($advertisement) use ($baseUrl) {
    //     if (!empty($advertisement->image)) {
    //         $advertisement->image = $baseUrl . '/' . ltrim($advertisement->image, '/');
    //     }
    //     return $advertisement;
    // });

    return response()->json([
        'data' => $advertisement,
        'message' => 'Advertisement retrieved successfully',
        'status' => 200,
        'error' => false,
    ], 200);
}

}
