<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Paymob API Key
    |--------------------------------------------------------------------------
    |
    | Your Paymob merchant API key used to authenticate and obtain auth tokens.
    |
    */
    'api_key' => env('PAYMOB_API_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | Paymob Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for all Paymob Accept API calls.
    |
    */
    'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com/api'),

    /*
    |--------------------------------------------------------------------------
    | Integration IDs
    |--------------------------------------------------------------------------
    |
    | Card and wallet integration IDs from your Paymob dashboard.
    |
    */
    'card_integration_id' => env('PAYMOB_CARD_INTEGRATION_ID', ''),
    'wallet_integration_id' => env('PAYMOB_WALLET_INTEGRATION_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | iFrame ID
    |--------------------------------------------------------------------------
    |
    | The iframe ID used to build the card checkout URL.
    |
    */
    'iframe_id' => env('PAYMOB_IFRAME_ID', ''),

    /*
    |--------------------------------------------------------------------------
    | HMAC Secret
    |--------------------------------------------------------------------------
    |
    | Used to verify the authenticity of Paymob callback/webhook requests.
    |
    */
    'hmac_secret' => env('PAYMOB_HMAC_SECRET', ''),

];
