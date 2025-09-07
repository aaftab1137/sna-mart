<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Admin\AdvertisementController;
use App\Http\Controllers\Admin\BoostingPlanController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\MembershipPlanController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Api\BoostingProductController;
use App\Http\Controllers\Api\HomeController;


Route::get('/health', function () {
    return response()->json(['status' => 'ok'], 200);
});

Route::get('/get_countries', [HomeController::class, 'get_countries'])->name('get_countries');
Route::get('/get_states/{id}', [HomeController::class, 'get_states'])->name('get_states');
Route::get('/get_cities/{id}', [HomeController::class, 'get_cities'])->name('get_cities');


Route::post('/verify_otp', [LoginController::class, 'verifyOtp']);
Route::post('/user/add/update', [UserController::class, 'createOrUpdateUser']);
Route::get('get_location', [LoginController::class, 'getLocation']);
Route::get('privacy_policy', [HomeController::class, 'privacy_policy']);
Route::get('terms_and_condition', [HomeController::class, 'terms_and_condition']);
Route::post('delete_fcm_token', [LoginController::class, 'deleteFcmToken']);
Route::post('/send_otp', [LoginController::class, 'sendOtpByMsg'])->name('signup');

Route::get('user/products/most-viewed', [ProductController::class, 'getMostViewedProducts']);


Route::group(['prefix' => 'user', 'as' => 'user.'], function () {
    //User
    Route::middleware('Login')->group(function () {

        Route::get('/profile', [UserController::class, 'profile']);
        Route::delete('user_delete', [UserController::class, 'userDelete']);

        //product
        Route::post('product/create', [ProductController::class, 'createProduct']);
        Route::get('product/rent/sell', [ProductController::class, 'getSellOrRentProduct']);
        Route::get('product/details/{id}', [ProductController::class, 'productDetails']);
        Route::post('product/update/{id}', [ProductController::class, 'updateProduct']);
        Route::delete('product/image/delete/{id}', [ProductController::class, 'productImageDelete']);
        Route::delete('product/delete/{id}', [ProductController::class, 'productDelete']);
        Route::get('my_product', [ProductController::class, 'getMyProduct']);
        // Route::get('products/most-viewed', [ProductController::class, 'getMostViewedProducts']);
        // Route::get('products/filter', [ProductController::class, 'filterProducts']);
        Route::post('sold_product/{id}', [ProductController::class, 'markAsSold']);


        //sponsor product
        Route::post('product/sponsor/{id}', [ProductController::class, 'sponsorProduct']);
        Route::get('products/sponsor', [ProductController::class, 'getSponsoredProduct']);
        Route::get('products/mysponsor', [ProductController::class, 'getMySponsoredProduct']);
        Route::delete('product/sponsor/remove/{id}', [ProductController::class, 'removeSponsorship']);


        //Wishlist
        Route::post('wishlist/add', [WishlistController::class, 'addWishlist']);
        Route::get('wishlist/all', [WishlistController::class, 'getWishlist']);
        Route::delete('wishlist/remove/{product_id}', [WishlistController::class, 'removeWishlist']);

        //rating app
        Route::post('rate_app', [UserController::class, 'submitRating']);
        Route::get('ratings', [UserController::class, 'getRatings']);

        //purchase Membership plan
        Route::get('plan/all', [MembershipPlanController::class, 'getAllMembershipPlans']);
        Route::post('purchase/plan', [UserController::class, 'purchaseMembershipPlan']);
        Route::get('active_plan', [UserController::class, 'viewMyActivePlan']);

        //all active plan per user
        Route::get('my_all_active_plans', [UserController::class, 'viewMyAllActivePlans']);
        Route::get('membership/invoice/{membershipId}', [UserController::class, 'downloadMembershipInvoice']);


        //Boost Product
        Route::post('boost/product/{product_id}', [BoostingProductController::class, 'boostProduct']);
        // Route::get('boost/product', [BoostingProductController::class, 'getBoostedProducts']);
        Route::get('boost/myproduct', [BoostingProductController::class, 'getMyBoostedProducts']);
        Route::delete('boost/remove/{product_id}', [BoostingProductController::class, 'removeBoost']);
        Route::get('boosting/invoice/{boosting_id}', [BoostingProductController::class, 'downloadBoostedProductInvoice']);
        // Route::get('boosting/product', [BoostingProductController::class,'productBoosting']);

        //get notification
        Route::get('notifications', [NotificationController::class, 'getNotifications']);

        Route::get('sponsored_links', [AdminController::class, 'getSponsoredLinks']);

        // recent search
        Route::post('recent_search/store', [ProductController::class, 'storeRecentSearch']);
        Route::get('recent_search', [ProductController::class, 'getRecentSearches']);
    });

    Route::get('boost/product', [BoostingProductController::class, 'getBoostedProducts']);
    Route::get('product/all', [ProductController::class, 'getProduct']);
    Route::get('products/filter', [ProductController::class, 'filterProducts']);
});

// Admin
Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {

    Route::post('/login', [AdminController::class, 'adminLogin']);

    Route::get('admin_dashboard', [AdminController::class, 'adminDashboard']);
    Route::middleware('AdminLogin')->group(function () {

        Route::get('get_all_users', [AdminController::class, 'getAllUsers']);
        // Route::get('/profile/{id}', [AdminController::class, 'profile']);
        Route::delete('user_delete/{id}', [AdminController::class, 'userDelete']);
        Route::get('product/all', [ProductController::class, 'getProduct']);
        Route::get('product/details/{id}', [ProductController::class, 'productDetails']);


        //productCategory
        Route::post('product/category/add', [ProductCategoryController::class, 'addProductCategory']);
        Route::post('product/category/update/{id}', [ProductCategoryController::class, 'updateProductCategory']);
        // Route::get('product/category', [ProductCategoryController::class, 'getProductCategory']);
        // Route::get('product/sub_category', [ProductCategoryController::class, 'getProductSubCategory']);
        // Route::get('product/sub_category/{id}', [ProductCategoryController::class, 'getSubCategory']);
        // Route::get('product/category/{id}', [ProductCategoryController::class, 'getSingleCategoryDetail']);
        Route::delete('product/category/delete/{id}', [ProductCategoryController::class, 'productCategoryDelete']);

        // approve reject product
        Route::get('product/all', [ProductController::class, 'getProduct']);
        Route::post('approve_reject_product/{id}', [AdminController::class, 'approveRejectProduct']);

        //Advertisement
        Route::post('advertisement/add', [AdvertisementController::class, 'addAdvertisement']);
        Route::post('advertisement/update/{id}', [AdvertisementController::class, 'updateAdvertisement']);
        Route::delete('advertisement/delete/{id}', [AdvertisementController::class, 'deleteAdvertisement']);
        Route::get('advertisement-detail/{id}', [AdvertisementController::class, 'getAdvertisementDetailById']);

        //Faq's
        Route::post('faq/add', [FaqController::class, 'addFaq']);
        Route::post('faq/update/{id}', [FaqController::class, 'updateFaq']);
        Route::post('faq/updateoradd', [FaqController::class, 'updateOrAddFaq']);
        Route::delete('faq/delete/{id}', [FaqController::class, 'deleteFaq']);
        Route::get('faq/list', [FaqController::class, 'getAllFaq']);
        Route::get('faq-details/{id}', [FaqController::class, 'getFaqDetailsById']);

        //Membership plan
        Route::post('plan/add', [MembershipPlanController::class, 'createMembershipPlan']);
        Route::post('plan/update/{id}', [MembershipPlanController::class, 'updateMembershipPlan']);
        Route::delete('plan/delete/{id}', [MembershipPlanController::class, 'deleteMembershipPlan']);
        Route::get('plan/all', [MembershipPlanController::class, 'getAllMembershipPlans']);
        Route::get('plan/{id}', [MembershipPlanController::class, 'getMembershipPlanById']);
        Route::get('purchased_plan/all', [MembershipPlanController::class, 'getPurchasedMembershipPlan']);

        //Boosting Plan
        Route::post('boosting_plan/add', [BoostingPlanController::class, 'createBoostingPlan']);
        Route::get('boosting_plan/all', [BoostingPlanController::class, 'getBoostingPlans']);
        Route::delete('boosting_plan/delete/{id}', [BoostingPlanController::class, 'deleteBoostingPlan']);
        Route::post('boosting_plan/update/{id}', [BoostingPlanController::class, 'updateBoostingPlan']);


        // Notification
        Route::post('send/notification', [NotificationController::class, 'sendNotification']);
        Route::delete('notification/delete/{id}', [NotificationController::class, 'deleteNotification']);
        Route::delete('all_notification/delete', [NotificationController::class, 'deleteAllNotifications']);
        Route::post('notification/status/update/{id}', [NotificationController::class, 'updateNotificationStatus']);

        // Contact detail
        Route::post('contact_detail/add/update', [AdminController::class, 'addUpdateContactDetail']);
        Route::get('contact_detail', [AdminController::class, 'getContactDetail']);

        Route::get('subscription_setting', [AdminController::class, 'getSubscriptionSettings']);
        Route::post('update/subscription-setting', [AdminController::class, 'updateSubscriptionSettings']);

        //sponsered
        Route::post('sponsored_links/add', [AdminController::class, 'addSponsoredLink']);
        Route::post('sponsored_links/update/{id}', [AdminController::class, 'updateSponsoredLink']);
        Route::delete('sponsored_links/delete/{id}', [AdminController::class, 'deleteSponsoredLink']);
        Route::get('sponsored_links', [AdminController::class, 'getSponsoredLinks']);

        Route::post('block_user/{id}', [AdminController::class, 'toggleBlockUser']);
        Route::post('delete_product/{id}', [AdminController::class, 'productDeleteByAdmin']);
    });

    //advertisement 
    Route::get('advertisement/all', [AdvertisementController::class, 'getAllAdvertisement']);
    Route::get('product/category', [ProductCategoryController::class, 'getProductCategory']);
    Route::get('product/sub_category', [ProductCategoryController::class, 'getProductSubCategory']);
    Route::get('product/sub_category/{id}', [ProductCategoryController::class, 'getSubCategory']);
    Route::get('product/category/{id}', [ProductCategoryController::class, 'getSingleCategoryDetail']);
    Route::get('/profile/{id}', [AdminController::class, 'profile']);
    
});
