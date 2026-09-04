<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth.nexus:passkey']], function () {
    Route::post('pieces-hash', [TorrentController::class, 'queryByPiecesHash'])->name('torrent.pieces_hash.query');
});

Route::post('challenge', [AuthenticateController::class, 'challenge'])
    ->middleware('throttle:third-party-auth');

Route::post('nastools/approve', [AuthenticateController::class, 'nasToolsApprove'])
    ->middleware('throttle:third-party-auth');
Route::post('iyuu/approve', [AuthenticateController::class, 'iyuuApprove'])
    ->middleware('throttle:third-party-auth');
Route::post('ammds/approve', [AuthenticateController::class, 'ammdsApprove'])
    ->middleware('throttle:third-party-auth');

// Legacy GET endpoint for IYUU backward compatibility (deprecated).
// Credentials in query string — will be removed after migration period.
Route::get('iyuu/approve', [AuthenticateController::class, 'iyuuApprove'])
    ->middleware('throttle:third-party-auth');
