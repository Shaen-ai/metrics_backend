<?php

/**
 * Routes here are served at the site root (no `/api` prefix).
 * Legacy fallback: signup emails point to `/api/auth/verify-email`; older links without `/api` still resolve here.
 */
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);
