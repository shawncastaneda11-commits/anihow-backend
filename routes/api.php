<?php

use App\Enums\Role;
use App\Http\Controllers\Api\Admin\CreateFarmerSellerController;
use App\Http\Controllers\Api\Auth\ChangePasswordController;
use App\Http\Controllers\Api\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\MeController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\ResendVerificationController;
use App\Http\Controllers\Api\Auth\ResetPasswordController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Cart\CartController;
use App\Http\Controllers\Api\Chat\OrderMessageController;
use App\Http\Controllers\Api\CropCare\CropCareArticleController;
use App\Http\Controllers\Api\Faq\FaqController;
use App\Http\Controllers\Api\Farms\FarmController;
use App\Http\Controllers\Api\Favorites\FavoriteController;
use App\Http\Controllers\Api\Listings\ListingController;
use App\Http\Controllers\Api\Listings\TawadRuleController;
use App\Http\Controllers\Api\Listings\ToggleListingActiveController;
use App\Http\Controllers\Api\Marketplace\CropTypeController;
use App\Http\Controllers\Api\Marketplace\MarketplaceController;
use App\Http\Controllers\Api\Notifications\NotificationController;
use App\Http\Controllers\Api\Orders\BuyerOrderController;
use App\Http\Controllers\Api\Orders\CheckoutController;
use App\Http\Controllers\Api\Orders\FarmerOrderController;
use App\Http\Controllers\Api\Orders\OrderHistoryController;
use App\Http\Controllers\Api\Orders\WalkInSaleController;
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

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('logout', LogoutController::class)->name('auth.logout');
        Route::get('user', MeController::class)->name('auth.user');
        Route::post('password', ChangePasswordController::class)->name('auth.password');
        Route::middleware('throttle:auth')->group(function (): void {
            Route::post('email/verification-notification', ResendVerificationController::class)
                ->name('verification.send');
            Route::post('email/verify', VerifyEmailController::class)
                ->name('verification.verify');
        });
    });
});

/*
 * Shared. The crop taxonomy and the crop-care library are reference content
 * that every authenticated actor reads. Crop-care is read-only here: it is
 * written by each farm's Content Editor in the CMS.
 */
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('crop-types', CropTypeController::class)->name('crop-types.index');

    Route::get('crop-care', [CropCareArticleController::class, 'index'])->name('crop-care.index');
    Route::get('crop-care/{cropCareArticle}', [CropCareArticleController::class, 'show'])
        ->name('crop-care.show');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount'])
        ->name('notifications.unread-count');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');
    Route::patch('notifications/{inAppNotification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    // Order chat is gated by OrderPolicy, not by role prefix. Buyer and
    // farmer-seller both hit the same routes for the same thread.
    Route::get('orders/{order}/messages', [OrderMessageController::class, 'index'])
        ->name('orders.messages.index');
    Route::post('orders/{order}/messages', [OrderMessageController::class, 'store'])
        ->name('orders.messages.store');

    Route::get('faq', [FaqController::class, 'index'])->name('faq.index');
    Route::post('faq/ask', [FaqController::class, 'ask'])->name('faq.ask');

    Route::get('farms/{farm}', FarmController::class)->name('farms.show');
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

    // Tawad: a seller-published peso discount rule, one active rule per listing.
    Route::post('listings/{listing}/tawad', [TawadRuleController::class, 'store'])
        ->name('farmer.listings.tawad.store');
    Route::delete('listings/{listing}/tawad/{tawadRule}', [TawadRuleController::class, 'destroy'])
        ->name('farmer.listings.tawad.destroy');

    Route::get('orders', [FarmerOrderController::class, 'index'])->name('farmer.orders.index');
    Route::get('orders/{order}', [FarmerOrderController::class, 'show'])->name('farmer.orders.show');
    Route::patch('orders/{order}/confirm', [FarmerOrderController::class, 'confirm'])
        ->name('farmer.orders.confirm');
    Route::patch('orders/{order}/ready', [FarmerOrderController::class, 'ready'])
        ->name('farmer.orders.ready');
    Route::patch('orders/{order}/complete', [FarmerOrderController::class, 'complete'])
        ->name('farmer.orders.complete');
    Route::patch('orders/{order}/cancel', [FarmerOrderController::class, 'cancel'])
        ->name('farmer.orders.cancel');

    // Walk-in sales: an in-person sale to someone without the app, recorded
    // after the handover. Lands in the same order ledger, directly at Completed.
    Route::post('walk-in-sales', WalkInSaleController::class)->name('farmer.walk-in-sales.store');

    Route::get('shop', [FarmerShopController::class, 'show'])->name('farmer.shop.show');
    Route::match(['put', 'patch'], 'shop', [FarmerShopController::class, 'update'])->name('farmer.shop.update');
});

Route::middleware([
    'auth:sanctum',
    RoleMiddleware::using(Role::Buyer, 'sanctum'),
])->prefix('buyer')->group(function (): void {
    Route::get('marketplace', [MarketplaceController::class, 'index'])->name('buyer.marketplace.index');
    Route::get('marketplace/{listing}', [MarketplaceController::class, 'show'])->name('buyer.marketplace.show');

    Route::get('cart', [CartController::class, 'index'])->name('buyer.cart.index');

    Route::get('orders', [BuyerOrderController::class, 'index'])->name('buyer.orders.index');
    Route::get('orders/history', [OrderHistoryController::class, 'index'])->name('buyer.orders.history');
    Route::get('orders/{order}', [BuyerOrderController::class, 'show'])->name('buyer.orders.show');
    Route::get('orders/{order}/receipt', [OrderHistoryController::class, 'receipt'])
        ->name('buyer.orders.receipt');

    Route::get('shops', [BuyerShopController::class, 'index'])->name('buyer.shops.index');
    Route::get('shops/{farmerSeller}', [BuyerShopController::class, 'show'])->name('buyer.shops.show');
    Route::get('shops/{farmerSeller}/reviews', [BuyerShopController::class, 'reviews'])
        ->name('buyer.shops.reviews');

    Route::get('favorites', [FavoriteController::class, 'index'])->name('buyer.favorites.index');

    Route::middleware('verified')->group(function (): void {
        Route::post('cart', [CartController::class, 'store'])->name('buyer.cart.store');
        Route::patch('cart/{cartItem}', [CartController::class, 'update'])->name('buyer.cart.update');
        Route::delete('cart/{cartItem}', [CartController::class, 'destroy'])->name('buyer.cart.destroy');

        // Splits the cart into one order per farmer-seller.
        Route::post('checkout', CheckoutController::class)->name('buyer.checkout');

        Route::patch('orders/{order}/cancel', [BuyerOrderController::class, 'cancel'])
            ->name('buyer.orders.cancel');

        Route::post('reviews', [ReviewController::class, 'store'])->name('buyer.reviews.store');
        Route::post('favorites', [FavoriteController::class, 'store'])->name('buyer.favorites.store');
        Route::delete('favorites/{listing}', [FavoriteController::class, 'destroy'])->name('buyer.favorites.destroy');
    });
});
