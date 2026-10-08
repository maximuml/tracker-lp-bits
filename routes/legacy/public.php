<?php

use App\Http\Controllers\CompatRedirectController;
use App\Http\Controllers\UtilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Renamed endpoints — the canonical GET URIs now live under /web/* in
// routes/web.php; 301 forwards the query string unchanged.
$get301 = static fn (string $from, string $to) => Route::get($from, [CompatRedirectController::class, 'get'])->defaults('redirect_to', $to);

// T-21: Static content pages — GET only (no POST handling in controllers)
$get301('/aboutnexus', '/web/aboutnexus');
$get301('/rules', '/web/rules');
$get301('/useragreement', '/web/useragreement');
$get301('/faq', '/web/faq');
Route::post('/faq', [CompatRedirectController::class, 'post'])->middleware('auth.nexus:nexus-web');
$get301('/donate', '/web/donate');
Route::post('/donate', [CompatRedirectController::class, 'post'])->middleware('auth.nexus:nexus-web');
$get301('/getusertorrentlistajax', '/web/getusertorrentlistajax');
$get301('/searchsuggest', '/web/searchsuggest');
Route::post('/ajax', [UtilityController::class, 'ajax'])->middleware(['throttle:ajax', 'reject.get.mutations'])->name('ajax.legacy');

$get301('/image', '/web/image');
$get301('/shoutbox_sse', '/web/shoutbox_sse');

$get301('/torrentrss', '/web/torrentrss');

$get301('/tags', '/web/tags');
$get301('/suggest', '/web/suggest');
$get301('/opensearch', '/web/opensearch');

Route::get('/confirmemail/{path?}', fn (Request $request) => redirect()->to(
    '/web/confirmemail'.($request->route('path') !== null && $request->route('path') !== '' ? '/'.$request->route('path') : '')
        .($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
))->where('path', '.*');
$get301('/cron', '/web/cron');
$get301('/ok', '/web/ok');

$get301('/complains', '/web/complains');
Route::post('/complains', [CompatRedirectController::class, 'post']);
$get301('/shoutbox', '/web/shoutbox');

$get301('/bookmark', '/web/bookmark');
Route::post('/bookmark', [CompatRedirectController::class, 'post'])->middleware(['auth.nexus:nexus-web', 'reject.get.mutations']);
$get301('/viewfilelist', '/web/viewfilelist');
$get301('/viewpeerlist', '/web/viewpeerlist');
