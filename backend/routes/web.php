<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'data' => [
        'service' => 'minerva-api',
        'status' => 'ok',
    ],
]));
