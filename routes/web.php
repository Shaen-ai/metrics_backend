<?php

/**
 * Routes here are served at the site root (no `/api` prefix).
 * Legacy fallback: signup emails point to `/api/auth/verify-email`; older links without `/api` still resolve here.
 */
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\VistaFilesController;
use Illuminate\Support\Facades\Route;

Route::get('/vista-files/{path}', [VistaFilesController::class, 'show'])
    ->where('path', '.*');

Route::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);

Route::get('/auth/social/{provider}/redirect', [SocialAuthController::class, 'redirect'])
    ->where('provider', 'google');
Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->where('provider', 'google');
