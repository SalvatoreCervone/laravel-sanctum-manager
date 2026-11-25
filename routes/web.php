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

Route::patch('/tokens/{tokenId}/scopes', [DashboardController::class, 'updateScopes'])
    ->name('sanctum-manager.dashboard.tokens.update-scopes');

Route::delete('/tokens/{tokenId}', [DashboardController::class, 'revoke'])
    ->name('sanctum-manager.dashboard.tokens.revoke');

Route::post('/tokens/revoke-user', [DashboardController::class, 'revokeUserTokens'])
    ->name('sanctum-manager.dashboard.tokens.revoke-user');

Route::post('/tokens/prune', [DashboardController::class, 'prune'])
    ->name('sanctum-manager.dashboard.tokens.prune');

Route::get('/scopes', [DashboardController::class, 'scopes'])
    ->name('sanctum-manager.dashboard.scopes');

Route::post('/scopes', [DashboardController::class, 'storeScope'])
    ->name('sanctum-manager.dashboard.scopes.store');

Route::post('/scopes/groups', [DashboardController::class, 'storeGroup'])
    ->name('sanctum-manager.dashboard.groups.store');

Route::delete('/scopes/groups/{groupName}', [DashboardController::class, 'destroyGroup'])
    ->where('groupName', '[a-zA-Z0-9:_-]+')
    ->name('sanctum-manager.dashboard.groups.destroy');

Route::delete('/scopes/{scopeName}', [DashboardController::class, 'destroyScope'])
    ->where('scopeName', '[a-zA-Z0-9:_.*-]+')
    ->name('sanctum-manager.dashboard.scopes.destroy');

Route::get('/logs', [DashboardController::class, 'logs'])
    ->name('sanctum-manager.dashboard.logs');

Route::get('/mail', [DashboardController::class, 'mailSettings'])
    ->name('sanctum-manager.dashboard.mail');

Route::post('/mail/test', [DashboardController::class, 'sendTestMail'])
    ->name('sanctum-manager.dashboard.mail.test');
