<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Low-stock threshold
    |--------------------------------------------------------------------------
    |
    | Farmer-sellers receive an in-app notification when a listing's
    | quantity_available crosses below this value after a reservation or POS sale.
    |
    */
    'low_stock_threshold' => (float) env('LOW_STOCK_THRESHOLD', 5),

    /*
    | Listing image disk. Use "public" locally (storage:link) and "s3" on Railway.
    */
    'listing_disk' => env('LISTING_DISK', 'public'),

    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:8000')),

    'force_https' => filter_var(
        env('FORCE_HTTPS', env('APP_ENV') === 'production' ? 'true' : 'false'),
        FILTER_VALIDATE_BOOLEAN,
    ),

    'rate_limit_auth' => (int) env('RATE_LIMIT_AUTH', 5),

    'rate_limit_api' => (int) env('RATE_LIMIT_API', 240),

    /*
    |--------------------------------------------------------------------------
    | Stale placed orders
    |--------------------------------------------------------------------------
    |
    | A placed order holds stock until the seller confirms or the buyer
    | cancels. After these many hours the seller is reminded once, then the
    | order is cancelled through the state machine if they still do not
    | respond.
    |
    */
    'order_reminder_after_hours' => (int) env('ORDER_REMINDER_AFTER_HOURS', 12),

    'order_auto_cancel_after_hours' => (int) env('ORDER_AUTO_CANCEL_AFTER_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | App login tokens
    |--------------------------------------------------------------------------
    |
    | Remembered phones keep a token for remember_days. A login without
    | remember expires after session_hours. The CMS login is separate.
    |
    */
    'auth' => [
        'remember_days' => (int) env('AUTH_REMEMBER_DAYS', 30),
        'session_hours' => (int) env('AUTH_SESSION_HOURS', 12),
        'temporary_password_days' => (int) env('ANIHOW_TEMP_PASSWORD_DAYS', 7),
    ],

    /*
    | Who a farmer or a farm emails when they want an account. Phone is
    | optional; an empty value is stored as null so the app can hide Call.
    */
    'seller_help' => [
        'email' => env('ANIHOW_SELLER_HELP_EMAIL', 'ict@lpu.edu.ph'),
        'phone' => env('ANIHOW_SELLER_HELP_PHONE') ?: null,
        'office' => env('ANIHOW_SELLER_HELP_OFFICE', 'LPU ICTD'),
    ],

];
