<?php

return [
    // Info rekening (override lewat env, fallback ke config ini)
    'bank_name' => env('BASECAMP_BANK_NAME', 'Bank Central Asia (BCA)'),
    'bank_account' => env('BASECAMP_BANK_ACCOUNT', '0000000000'),
    'bank_holder' => env('BASECAMP_BANK_HOLDER', 'Basecamp Military Lifestyle'),

    'check_in_time' => env('BASECAMP_CHECKIN_TIME', '14:00'),
    'check_out_time' => env('BASECAMP_CHECKOUT_TIME', '12:00'),

    // Tujuan notifikasi operasional
    'admin_email' => env('BASECAMP_ADMIN_EMAIL'),
    'admin_whatsapp' => env('BASECAMP_ADMIN_WHATSAPP'),
];