<?php

use App\Enums\Role;
use App\Http\Controllers\Api\Admin\CreateFarmerSellerController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendVerificationController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\CropCare\CropCareArticleController;
use App\Http\Controllers\Api\Favorites\FavoriteController;
use App\Http\Controllers\Api\Listings\ListingController;
use App\Http\Controllers\Api\Listings\ToggleListingActiveController;
use App\Http\Controllers\Api\Marketplace\CategoryController;
use App\Http\Controllers\Api\Marketplace\MarketplaceController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\Orders\OrderHistoryController;
use App\Http\Controllers\Api\Pos\SaleController;
use App\Http\Controllers\Api\Reservations\BuyerReservationController;
use App\Http\Controllers\Api\Reservations\FarmerReservationController;
use App\Http\Controllers\Api\Reviews\ReviewController;
use App\Http\Controllers\Api\Shop\BuyerShopController;
use App\Http\Controllers\Api\Shop\FarmerShopController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\RoleMiddleware;

Route::prefix('auth')->group(function (): void {
    Route::middleware('throttle:auth')->group(function (): void {
        Route::post('register', RegisterController::class)->name('auth.register');
        Route::post('login', LoginController::class)->name('auth.login');
        Route::post('forgot-password', ForgotPasswordController::class)->name('auth.forgot-password');
        Route::post('reset-password', ResetPasswordController::class)->name('auth.reset-password');
    });

    Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware('signed')
        ->name('verification.verify');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('auth.logout');
        Route::get('user', MeController::class)->name('auth.user');
        Route::post('email/verification-notification', ResendVerificationController::class)
            ->middleware('throttle:auth')
            ->name('verification.send');
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('categories', CategoryController::class)->name('categories.index');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/{inAppNotification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
});

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::using(Role::SuperAdmin, 'sanctum'),
])->prefix('admin')->group(function (): void {
    Route::post('farmer-sellers', CreateFarmerSellerController::class)
        ->name('admin.farmer-sellers.store');
});

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::using(Role::FarmerSeller, 'sanctum'),
])->prefix('farmer')->group(function (): void {
    Route::get('listings', [ListingController::class, 'index'])->name('farmer.listings.index');
    Route::post('listings', [ListingController::class, 'store'])->name('farmer.listings.store');
    Route::get('listings/{listing}', [ListingController::class, 'show'])->name('farmer.listings.show');
    Route::match(['put', 'patch', 'post'], 'listings/{listing}', [ListingController::class, 'update'])
        ->name('farmer.listings.update');
    Route::delete('listings/{listing}', [ListingController::class, 'destroy'])->name('farmer.listings.destroy');
    Route::patch('listings/{listing}/active', ToggleListingActiveController::class)
        ->name('farmer.listings.toggle-active');

    Route::get('reservations', [FarmerReservationController::class, 'index'])->name('farmer.reservations.index');
    Route::get('reservations/{reservation}', [FarmerReservationController::class, 'show'])->name('farmer.reservations.show');
    Route::patch('reservations/{reservation}/ready', [FarmerReservationController::class, 'ready'])
        ->name('farmer.reservations.ready');
    Route::patch('reservations/{reservation}/complete', [FarmerReservationController::class, 'complete'])
        ->name('farmer.reservations.complete');
    Route::patch('reservations/{reservation}/cancel', [FarmerReservationController::class, 'cancel'])
        ->name('farmer.reservations.cancel');

    Route::get('sales', [SaleController::class, 'index'])->name('farmer.sales.index');
    Route::post('sales', [SaleController::class, 'store'])->name('farmer.sales.store');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->name('farmer.sales.show');
    Route::delete('sales/{sale}', [SaleController::class, 'destroy'])->name('farmer.sales.destroy');

    Route::get('crop-care/categories', [CropCareArticleController::class, 'categories'])
        ->name('farmer.crop-care.categories');
    Route::get('crop-care/mine', [CropCareArticleController::class, 'mine'])
        ->name('farmer.crop-care.mine');
    Route::get('crop-care', [CropCareArticleController::class, 'index'])->name('farmer.crop-care.index');
    Route::post('crop-care', [CropCareArticleController::class, 'store'])->name('farmer.crop-care.store');
    Route::get('crop-care/{cropCareArticle}', [CropCareArticleController::class, 'show'])
        ->name('farmer.crop-care.show');
    Route::match(['put', 'patch', 'post'], 'crop-care/{cropCareArticle}', [CropCareArticleController::class, 'update'])
        ->name('farmer.crop-care.update');
    Route::delete('crop-care/{cropCareArticle}', [CropCareArticleController::class, 'destroy'])
        ->name('farmer.crop-care.destroy');

    Route::get('shop', [FarmerShopController::class, 'show'])->name('farmer.shop.show');
    Route::match(['put', 'patch'], 'shop', [FarmerShopController::class, 'update'])->name('farmer.shop.update');
});

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::using(Role::Buyer, 'sanctum'),
])->prefix('buyer')->group(function (): void {
    Route::get('marketplace', [MarketplaceController::class, 'index'])->name('buyer.marketplace.index');
    Route::get('marketplace/{listing}', [MarketplaceController::class, 'show'])->name('buyer.marketplace.show');

    Route::get('reservations', [BuyerReservationController::class, 'index'])->name('buyer.reservations.index');
    Route::get('reservations/{reservation}', [BuyerReservationController::class, 'show'])->name('buyer.reservations.show');

    Route::get('orders', [OrderHistoryController::class, 'index'])->name('buyer.orders.index');
    Route::get('orders/{reservation}/receipt', [OrderHistoryController::class, 'receipt'])
        ->name('buyer.orders.receipt');

    Route::get('shops', [BuyerShopController::class, 'index'])->name('buyer.shops.index');
    Route::get('shops/{farmerSeller}', [BuyerShopController::class, 'show'])->name('buyer.shops.show');
    Route::get('shops/{farmerSeller}/reviews', [BuyerShopController::class, 'reviews'])
        ->name('buyer.shops.reviews');

    Route::get('favorites', [FavoriteController::class, 'index'])->name('buyer.favorites.index');

    Route::middleware('verified')->group(function (): void {
        Route::post('reservations', [BuyerReservationController::class, 'store'])->name('buyer.reservations.store');
        Route::patch('reservations/{reservation}/cancel', [BuyerReservationController::class, 'cancel'])
            ->name('buyer.reservations.cancel');
        Route::post('reviews', [ReviewController::class, 'store'])->name('buyer.reviews.store');
        Route::post('favorites', [FavoriteController::class, 'store'])->name('buyer.favorites.store');
        Route::delete('favorites/{listing}', [FavoriteController::class, 'destroy'])->name('buyer.favorites.destroy');
    });
});
