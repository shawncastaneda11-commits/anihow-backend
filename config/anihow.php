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

    'rate_limit_api' => (int) env('RATE_LIMIT_API', 60),

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

];
