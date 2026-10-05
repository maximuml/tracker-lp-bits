<?php

use App\Http\Controllers\FaqController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TorrentBookmarkController;
use App\Http\Controllers\UtilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Renamed endpoints — the canonical GET URIs now live under /web/* in
// routes/web.php; 301 forwards the query string unchanged.
$redirect301 = fn (string $target) => fn (Request $request) => redirect()->to(
    $target.($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
);

// T-21: Static content pages — GET only (no POST handling in controllers)
Route::get('/aboutnexus', $redirect301('/web/aboutnexus'));
Route::get('/rules', $redirect301('/web/rules'));
Route::get('/useragreement', $redirect301('/web/useragreement'));
Route::get('/faq', $redirect301('/web/faq'));
Route::post('/faq', [FaqController::class, 'faqPost'])->middleware('auth.nexus:nexus-web');
Route::get('/donate', $redirect301('/web/donate'));
Route::post('/donate', [InfoController::class, 'donatePost'])->middleware('auth.nexus:nexus-web');
Route::get('/getusertorrentlistajax', $redirect301('/web/getusertorrentlistajax'));
Route::get('/searchsuggest', $redirect301('/web/searchsuggest'));
Route::post('/ajax', [UtilityController::class, 'ajax'])->middleware(['throttle:ajax', 'reject.get.mutations'])->name('ajax.legacy');

Route::get('/image', $redirect301('/web/image'));
Route::get('/shoutbox_sse', $redirect301('/web/shoutbox_sse'));

Route::get('/torrentrss', $redirect301('/web/torrentrss'));

Route::get('/tags', $redirect301('/web/tags'));
Route::get('/suggest', $redirect301('/web/suggest'));
Route::get('/opensearch', $redirect301('/web/opensearch'));

Route::get('/confirmemail/{path?}', fn (Request $request) => redirect()->to(
    '/web/confirmemail'.($request->route('path') !== null && $request->route('path') !== '' ? '/'.$request->route('path') : '')
        .($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
))->where('path', '.*');
Route::get('/cron', $redirect301('/web/cron'));
Route::get('/ok', $redirect301('/web/ok'));

Route::get('/complains', $redirect301('/web/complains'));
Route::post('/complains', [SupportController::class, 'complainsPost']);
Route::get('/shoutbox', $redirect301('/web/shoutbox'));

Route::get('/bookmark', $redirect301('/web/bookmark'));
Route::post('/bookmark', [TorrentBookmarkController::class, 'bookmarkToggle'])->middleware(['auth.nexus:nexus-web', 'reject.get.mutations']);
Route::get('/viewfilelist', $redirect301('/web/viewfilelist'));
Route::get('/viewpeerlist', $redirect301('/web/viewpeerlist'));
