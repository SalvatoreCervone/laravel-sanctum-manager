<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use SanctumManager\Http\Controllers\DashboardController;

Route::get('/', [DashboardController::class, 'index'])
    ->name('sanctum-manager.dashboard.index');

Route::post('/tokens', [DashboardController::class, 'issue'])
    ->name('sanctum-manager.dashboard.tokens.issue');

Route::post('/tokens/{tokenId}/rotate', [DashboardController::class, 'rotate'])
    ->name('sanctum-manager.dashboard.tokens.rotate');

Route::delete('/tokens/{tokenId}', [DashboardController::class, 'revoke'])
    ->name('sanctum-manager.dashboard.tokens.revoke');

Route::post('/tokens/revoke-user', [DashboardController::class, 'revokeUserTokens'])
    ->name('sanctum-manager.dashboard.tokens.revoke-user');

Route::post('/tokens/prune', [DashboardController::class, 'prune'])
    ->name('sanctum-manager.dashboard.tokens.prune');

Route::get('/scopes', [DashboardController::class, 'scopes'])
    ->name('sanctum-manager.dashboard.scopes');

Route::get('/logs', [DashboardController::class, 'logs'])
    ->name('sanctum-manager.dashboard.logs');

Route::get('/mail', [DashboardController::class, 'mailSettings'])
    ->name('sanctum-manager.dashboard.mail');

Route::post('/mail/test', [DashboardController::class, 'sendTestMail'])
    ->name('sanctum-manager.dashboard.mail.test');
