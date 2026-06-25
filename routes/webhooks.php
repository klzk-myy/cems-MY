<?php

use App\Http\Controllers\Api\SanctionsWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/sanctions/update', [SanctionsWebhookController::class, '__invoke'])
    ->name('api.v1.webhooks.sanctions.update');

Route::get('/sanctions/health', [SanctionsWebhookController::class, 'health'])
    ->middleware('throttle:30,1')
    ->name('api.v1.webhooks.sanctions.health');
