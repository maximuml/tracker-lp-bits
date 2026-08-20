<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\TorrentController;
use Illuminate\Support\Facades\Route;

Route::group(['middleware' => ['auth.nexus:passkey']], function () {
    Route::post('pieces-hash', [TorrentController::class, 'queryByPiecesHash'])->name('torrent.pieces_hash.query');
});

Route::post('challenge', [AuthenticateController::class, 'challenge']);

Route::post('nastools/approve', [AuthenticateController::class, 'nasToolsApprove']);
Route::get('iyuu/approve', [AuthenticateController::class, 'iyuuApprove']);
Route::post('ammds/approve', [AuthenticateController::class, 'ammdsApprove']);
