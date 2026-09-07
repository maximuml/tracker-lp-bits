<?php

use App\Http\Controllers\FaqController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SystemMaintenanceController;
use App\Http\Controllers\TorrentAjaxController;
use App\Http\Controllers\TorrentBookmarkController;
use App\Http\Controllers\TorrentRssController;
use App\Http\Controllers\UtilityController;
use Illuminate\Support\Facades\Route;

// T-21: Static content pages — GET only (no POST handling in controllers)
Route::get('/aboutnexus', [RulesController::class, 'aboutNexus'])->name('aboutnexus.legacy');
Route::get('/rules', [RulesController::class, 'rules'])->name('rules.legacy');
Route::get('/useragreement', [RulesController::class, 'userAgreement'])->name('useragreement.legacy');
Route::get('/faq', [FaqController::class, 'faq'])->name('faq.legacy');
Route::post('/faq', [FaqController::class, 'faq']);
Route::get('/donate', [InfoController::class, 'donate'])->name('donate.legacy');
Route::post('/donate', [InfoController::class, 'donate']);
Route::get('/getusertorrentlistajax', [TorrentAjaxController::class, 'getUserTorrentListAjax'])->name('getusertorrentlistajax.legacy');
Route::get('/searchsuggest', [TorrentAjaxController::class, 'searchSuggest'])->name('searchsuggest.legacy');
Route::post('/ajax', [UtilityController::class, 'ajax'])->middleware(['throttle:ajax', 'reject.get.mutations'])->name('ajax.legacy');

Route::get('/image', [UtilityController::class, 'image'])->name('image.legacy');
Route::get('/shoutbox_sse', [ShoutboxController::class, 'shoutboxSse'])->name('shoutbox_sse.legacy');

Route::get('/torrentrss', [TorrentRssController::class, 'torrentrss'])->name('torrentrss.legacy');

Route::get('/page', [UtilityController::class, 'page'])->name('page.legacy');
Route::get('/tags', [UtilityController::class, 'tags'])->name('tags.legacy');
Route::get('/suggest', [UtilityController::class, 'suggest'])->name('suggest.legacy');
Route::get('/opensearch', [UtilityController::class, 'opensearch'])->name('opensearch.legacy');

Route::get('/confirmemail/{path?}', [UtilityController::class, 'confirmemail'])->where('path', '.*')->name('confirmemail.legacy');
Route::get('/cron', [SystemMaintenanceController::class, 'cron'])->middleware('cron.token')->name('cron.legacy');
Route::get('/ok', [UtilityController::class, 'ok'])->name('ok.legacy');

Route::get('/complains', [SupportController::class, 'complains'])->name('complains.legacy');
Route::post('/complains', [SupportController::class, 'complains']);
Route::get('/shoutbox', [ShoutboxController::class, 'shoutbox'])->middleware('throttle:shoutbox')->name('shoutbox.legacy');
Route::post('/shoutbox', [ShoutboxController::class, 'shoutbox'])->middleware('throttle:shoutbox');

Route::get('/bookmark', [TorrentBookmarkController::class, 'bookmark'])->name('bookmark.legacy');
Route::post('/bookmark', [TorrentBookmarkController::class, 'bookmark']);
Route::get('/viewfilelist', [TorrentAjaxController::class, 'viewFileList'])->name('viewfilelist.legacy');
Route::get('/viewpeerlist', [TorrentAjaxController::class, 'viewPeerList'])->name('viewpeerlist.legacy');
