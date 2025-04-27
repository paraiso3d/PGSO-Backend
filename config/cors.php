<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    */

    // You want to allow CORS not only for APIs, but also for public images
    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
        'public/img/*', // <- add this if your route is public/img/*
    ],

    'allowed_methods' => ['*'], // allow all HTTP methods (GET, POST, etc.)

    'allowed_origins' => [
        'http://localhost:3000', // <- allow only your frontend
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // allow all headers

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
