<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::get('/produk', [
        \App\Http\Controllers\Api\V1\ProdukController::class,
        'index',
    ]);

    Route::get('/produk/{produk:sku}', [
        \App\Http\Controllers\Api\V1\ProdukController::class,
        'show',
    ]);
});

Route::post('/v1/produk', [
    \App\Http\Controllers\Api\V1\ProdukController::class,
    'store',
]);

Route::post(
    '/v1/produk/{produk:sku}/penyesuaian-stok',
    \App\Http\Controllers\Api\V1\StokController::class
);
