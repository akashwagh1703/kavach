<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name' => 'KAVACH',
        'api' => '/api/v1',
    ]);
});
