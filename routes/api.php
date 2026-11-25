<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SanctumManager\Http\Controllers\ScopeController;
use SanctumManager\Http\Controllers\TokenController;

Route::get('/scopes', [ScopeController::class, 'index'])
    ->name('sanctum-manager.scopes.index');

Route::get('/tokens', [TokenController::class, 'index'])
    ->name('sanctum-manager.tokens.index');

Route::post('/tokens', [TokenController::class, 'store'])
    ->name('sanctum-manager.tokens.store');

Route::post('/tokens/{tokenId}/rotate', [TokenController::class, 'rotate'])
    ->name('sanctum-manager.tokens.rotate');

Route::delete('/tokens/{tokenId}', [TokenController::class, 'destroy'])
    ->name('sanctum-manager.tokens.destroy');
