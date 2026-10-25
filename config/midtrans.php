<?php

return [
    /*
     * Gateway hanya aktif bila semua kredensial terisi.
     * Selama kosong, sistem otomatis memakai transfer manual.
     */
    'enabled' => env('MIDTRANS_ENABLED', false),

    'merchant_id' => env('MIDTRANS_MERCHANT_ID', ''),

    'client_key' => env('MIDTRANS_CLIENT_KEY', ''),

    'server_key' => env('MIDTRANS_SERVER_KEY', ''),

    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),

    'snap_url' => env('MIDTRANS_SNAP_URL', 'https://app.sandbox.midtrans.com/snap/snap.js'),

    'finish_redirect' => env('MIDTRANS_FINISH_REDIRECT', '/payment/return'),
];