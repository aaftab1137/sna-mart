<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/terms_condition', function () {
    return view('terms_condition');
});

Route::get('/privacy_policy', function () {
    return view('privacy');
});
