<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Fee Percentage
    |--------------------------------------------------------------------------
    |
    | The percentage Athar keeps from each completed paid help request.
    | E.g. 30 means 30% goes to the platform, 70% to the volunteer.
    |
    */
    'platform_fee_percentage' => (int) env('ATHAR_PLATFORM_FEE_PCT', 30),

    /*
    |--------------------------------------------------------------------------
    | Earnings Clearance Delay (days)
    |--------------------------------------------------------------------------
    |
    | Number of days after completion before card-payment earnings are
    | considered "cleared". Cash earnings are cleared immediately.
    |
    */
    'clearance_delay_days' => (int) env('ATHAR_CLEARANCE_DELAY_DAYS', 3),

    /*
    |--------------------------------------------------------------------------
    | Admin Dashboard Cache Duration (seconds)
    |--------------------------------------------------------------------------
    |
    | Time-to-live for cached /admin/dashboard payload. Set to 0 to disable
    | dashboard caching.
    |
    */
    'dashboard_cache_seconds' => (int) env('ATHAR_DASHBOARD_CACHE_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Admin Lists Cache Duration (seconds)
    |--------------------------------------------------------------------------
    |
    | Time-to-live for cached admin index/list endpoints such as locations,
    | help requests, place submissions, and accounts. Set to 0 to disable.
    |
    */
    'admin_list_cache_seconds' => (int) env('ATHAR_ADMIN_LIST_CACHE_SECONDS', 60),

];
