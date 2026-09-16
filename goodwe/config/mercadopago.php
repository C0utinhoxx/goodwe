<?php
// config/mercadopago.php
return [
    'access_token' => env('MP_ACCESS_TOKEN'),
    'public_key' => env('MP_PUBLIC_KEY'),
    'webhook_secret' => env('MP_WEBHOOK_SECRET'),
];