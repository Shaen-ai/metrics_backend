<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ColorDetectorController;
use App\Http\Controllers\ImageProxyController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTemplateController;
use App\Http\Controllers\ModeController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Billing\StripeCheckoutController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\InternalUsageConsumeController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\UsageConsumeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => config('mail.from.name').' API',
        'version' => '1.0.0',
        'status' => 'running',
    ]);
});

/*
|--------------------------------------------------------------------------
| Auth Routes
|--------------------------------------------------------------------------
*/
Route::post('/auth/register', [AuthController::class, 'register']);
Route::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateProfile']);
    Route::get('/auth/subdomain-availability', [AuthController::class, 'subdomainAvailability']);
    /** Needed for onboarding (choose mode/sub-modes before other subscribed features). */
    Route::get('/modes', [ModeController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::post('/auth/publish-site', [AuthController::class, 'publishSite']);
    Route::post('/usage/consume', [UsageConsumeController::class, 'consume']);

    Route::post('/upload-image', [\App\Http\Controllers\UploadController::class, 'store']);
    Route::post('/upload-material-image', [\App\Http\Controllers\UploadController::class, 'storeMaterialImage']);
    Route::post('/upload-model', [\App\Http\Controllers\UploadController::class, 'storeModel']);
    Route::post('/download-remote-model', [\App\Http\Controllers\UploadController::class, 'downloadRemoteModel']);

    Route::apiResource('catalog-items', CatalogItemController::class);

    Route::get('/material-templates', [MaterialTemplateController::class, 'index']);
    Route::post('/materials/import-templates', [MaterialController::class, 'importFromTemplates']);
    Route::patch('/materials/bulk', [MaterialController::class, 'bulkUpdate']);
    Route::apiResource('materials', MaterialController::class);

    Route::post('/detect-color', [ColorDetectorController::class, 'detect']);
    Route::apiResource('modules', ModuleController::class);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);

    Route::get('/planner/history', [PlannerController::class, 'history']);
});

/*
|--------------------------------------------------------------------------
| Public global (must be before public/{slug} or "material-templates" is parsed as a slug)
|--------------------------------------------------------------------------
*/
Route::get('/public/material-templates', [MaterialTemplateController::class, 'index']);

Route::post('/contact', [ContactFormController::class, 'store'])
    ->middleware('throttle:20,1');

/*
|--------------------------------------------------------------------------
| Public Routes (by admin slug)
|--------------------------------------------------------------------------
*/
Route::prefix('public/{slug}')->group(function () {
    Route::get('/', [PublicController::class, 'admin']);
    Route::get('/entitlements', [PublicController::class, 'entitlements']);
    Route::get('/catalog', [PublicController::class, 'catalog']);
    Route::get('/materials', [PublicController::class, 'materials']);
    Route::get('/modules', [PublicController::class, 'modules']);
    Route::post('/orders', [PublicController::class, 'submitOrder']);
});

Route::post('/internal/usage/consume', [InternalUsageConsumeController::class, 'consume']);

Route::post('/planner/generate', [PlannerController::class, 'generate']);

Route::get('/image-proxy', ImageProxyController::class);

Route::post('/paypal/ipn', [PaypalController::class, 'ipn']);

Route::get('/billing/checkout', [StripeCheckoutController::class, 'redirect']);
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

