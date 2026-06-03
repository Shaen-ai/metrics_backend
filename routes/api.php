<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Billing\StripeCheckoutController;
use App\Http\Controllers\Billing\StripePortalController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Billing\TokenTopUpCheckoutController;
use App\Http\Controllers\CatalogItemController;
use App\Http\Controllers\ColorDetectorController;
use App\Http\Controllers\ContactFormController;
use App\Http\Controllers\DesignProjectController;
use App\Http\Controllers\ErrorReportController;
use App\Http\Controllers\ImageProxyController;
use App\Http\Controllers\InteriorDesignController;
use App\Http\Controllers\InternalNotificationController;
use App\Http\Controllers\InternalTokenConsumeController;
use App\Http\Controllers\InternalUsageConsumeController;
use App\Http\Controllers\LiveSearchController;
use App\Http\Controllers\MarketplaceProductController;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\MaterialTemplateController;
use App\Http\Controllers\ModeController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaypalController;
use App\Http\Controllers\PlannerController;
use App\Http\Controllers\PlannerCustomDesignController;
use App\Http\Controllers\PublicCatalogSlotController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\PublicReferralController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UsageConsumeController;
use App\Http\Controllers\VistaProjectController;
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
Route::get('/tokens/balance', [TokenController::class, 'balance']);
Route::post('/tokens/anonymous/grant', [TokenController::class, 'grantAnonymous']);
Route::post('/tokens/check', [TokenController::class, 'check']);
Route::post('/tokens/consume', [TokenController::class, 'consume']);

Route::get('/public/referral/{code}', [PublicReferralController::class, 'show']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/register-consumer', [AuthController::class, 'registerConsumer']);
Route::get('/auth/verify-email', [AuthController::class, 'verifyEmail']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/oauth/exchange', [SocialAuthController::class, 'exchange']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/tokens/referral-link', [TokenController::class, 'referralLink']);
    Route::post('/tokens/top-up/checkout', [TokenTopUpCheckoutController::class, 'create']);
    Route::get('/tokens/top-up/verify', [TokenTopUpCheckoutController::class, 'verify']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/me', [AuthController::class, 'updateProfile']);
    Route::get('/auth/subdomain-availability', [AuthController::class, 'subdomainAvailability']);
    Route::post('/billing/portal', [StripePortalController::class, 'create']);
    /** Needed for onboarding (choose mode/sub-modes before other subscribed features). */
    Route::get('/modes', [ModeController::class, 'index']);

    // Vista saved projects (consumer)
    Route::get('/vista/projects', [VistaProjectController::class, 'index']);
    Route::post('/vista/projects', [VistaProjectController::class, 'store']);
    Route::get('/vista/projects/{id}', [VistaProjectController::class, 'show']);
    Route::patch('/vista/projects/{id}', [VistaProjectController::class, 'update']);
    Route::delete('/vista/projects/{id}', [VistaProjectController::class, 'destroy']);
    Route::post('/vista/projects/{id}/versions', [VistaProjectController::class, 'addVersion']);
    Route::post('/vista/projects/{id}/messages', [VistaProjectController::class, 'addMessage']);
});

Route::middleware(['auth:sanctum', 'subscribed'])->group(function () {
    Route::post('/auth/publish-site', [AuthController::class, 'publishSite']);
    Route::post('/usage/consume', [UsageConsumeController::class, 'consume']);

    Route::post('/upload-image', [UploadController::class, 'store']);
    Route::post('/upload-material-image', [UploadController::class, 'storeMaterialImage']);
    Route::post('/upload-model', [UploadController::class, 'storeModel']);
    Route::post('/download-remote-model', [UploadController::class, 'downloadRemoteModel']);

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

    Route::post('/error-report', [ErrorReportController::class, 'store'])
        ->middleware('throttle:10,1');

    Route::get('/planner/history', [PlannerController::class, 'history']);
    Route::post('/planners/custom-design/save', [PlannerCustomDesignController::class, 'save']);
    Route::get('/planners/custom-design/load', [PlannerCustomDesignController::class, 'load']);
    Route::post('/planners/custom-design/submit', [PlannerCustomDesignController::class, 'submit']);

    Route::post('/interior-design/sessions', [InteriorDesignController::class, 'createSession']);
    Route::get('/interior-design/sessions', [InteriorDesignController::class, 'listSessions']);
    Route::get('/interior-design/sessions/{id}', [InteriorDesignController::class, 'showSession']);
    Route::post('/interior-design/sessions/{id}/images', [InteriorDesignController::class, 'addImages']);
    Route::delete('/interior-design/sessions/{id}', [InteriorDesignController::class, 'deleteSession']);
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
| Marketplace (public — Vista + scraped_products catalog)
|--------------------------------------------------------------------------
| Register /products/search and /products/by-ids before /products/{id}.
*/
Route::prefix('marketplace')->group(function () {
    Route::get('/products/search', [MarketplaceProductController::class, 'search']);
    Route::get('/products/browse', [MarketplaceProductController::class, 'browse']);
    Route::post('/products/resolve-intents', [MarketplaceProductController::class, 'resolveIntents']);
    Route::post('/products/resolve-slots', [MarketplaceProductController::class, 'resolveSlots']);
    Route::post('/products/deactivate', [MarketplaceProductController::class, 'deactivate']);
    Route::get('/products/by-ids', [MarketplaceProductController::class, 'byIds']);
    Route::get('/products/{id}', [MarketplaceProductController::class, 'show'])->whereNumber('id');
    Route::get('/stats', [MarketplaceProductController::class, 'stats']);

    Route::post('/live-search', [LiveSearchController::class, 'search']);
    Route::get('/countries', [LiveSearchController::class, 'countries']);
    Route::get('/detect-country', [LiveSearchController::class, 'detectCountry']);
});

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
    Route::post('/planner-inquiry', [PublicController::class, 'plannerInquiry'])
        ->middleware('throttle:12,1');
    Route::post('/planners/custom-design/submit', [PlannerCustomDesignController::class, 'submitPublic'])
        ->middleware('throttle:12,1');
    Route::post('/orders', [PublicController::class, 'submitOrder']);
    Route::post('/planner-surface-image', [UploadController::class, 'storePlannerSurfaceImage'])
        ->middleware('throttle:20,1');
});

Route::post('/internal/usage/consume', [InternalUsageConsumeController::class, 'consume']);
Route::post('/internal/notify/slot-failure', [InternalNotificationController::class, 'slotFailure']);
Route::post('/internal/tokens/consume', [InternalTokenConsumeController::class, 'consume']);
Route::post('/internal/design-projects/{id}/update', [DesignProjectController::class, 'internalUpdate']);
Route::post('/internal/design-projects/{id}/images', [DesignProjectController::class, 'internalStoreImage']);
Route::post('/internal/design-projects/{id}/pdf', [DesignProjectController::class, 'internalStorePdf']);

Route::get('/interior-design/images/{id}', [InteriorDesignController::class, 'serveImage']);
Route::post('/public/{slug}/interior-design/upload', [InteriorDesignController::class, 'publicUpload']);
Route::post('/public/{slug}/catalog/resolve-slots', [PublicCatalogSlotController::class, 'resolveSlots']);

Route::post('/planner/generate', [PlannerController::class, 'generate']);

Route::get('/image-proxy', ImageProxyController::class);

Route::post('/paypal/ipn', [PaypalController::class, 'ipn']);

Route::get('/billing/checkout', [StripeCheckoutController::class, 'redirect']);
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
