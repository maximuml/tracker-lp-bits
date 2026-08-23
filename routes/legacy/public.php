<?php

use App\Http\Controllers\FaqController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\RulesController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SystemMaintenanceController;
use App\Http\Controllers\TorrentAjaxController;
use App\Http\Controllers\TorrentBookmarkController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentRssController;
use App\Http\Controllers\UtilityController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'], '/aboutnexus', [RulesController::class, 'aboutNexus'])->name('aboutnexus.legacy');
Route::match(['get', 'post'], '/rules', [RulesController::class, 'rules'])->name('rules.legacy');
Route::match(['get', 'post'], '/useragreement', [RulesController::class, 'userAgreement'])->name('useragreement.legacy');
Route::match(['get', 'post'], '/faq', [FaqController::class, 'faq'])->name('faq.legacy');
Route::match(['get', 'post'], '/donate', [InfoController::class, 'donate'])->name('donate.legacy');
Route::match(['get', 'post'], '/getusertorrentlistajax', [TorrentAjaxController::class, 'getUserTorrentListAjax'])->name('getusertorrentlistajax.legacy');
Route::match(['get', 'post'], '/searchsuggest', [TorrentAjaxController::class, 'searchSuggest'])->name('searchsuggest.legacy');
Route::match(['get', 'post'], '/ajax', [UtilityController::class, 'ajax'])->middleware('throttle:ajax')->name('ajax.legacy');

Route::match(['get', 'post'], '/image', [UtilityController::class, 'image'])->name('image.legacy');
Route::match(['get', 'post'], '/shoutbox_sse', [ShoutboxController::class, 'shoutboxSse'])->name('shoutbox_sse.legacy');

Route::match(['get', 'post'], '/torrentrss', [TorrentRssController::class, 'torrentrss'])->name('torrentrss.legacy');

Route::match(['get', 'post'], '/page', [UtilityController::class, 'page'])->name('page.legacy');
Route::match(['get', 'post'], '/tags', [UtilityController::class, 'tags'])->name('tags.legacy');
Route::match(['get', 'post'], '/suggest', [UtilityController::class, 'suggest'])->name('suggest.legacy');
Route::match(['get', 'post'], '/opensearch', [UtilityController::class, 'opensearch'])->name('opensearch.legacy');

Route::match(['get', 'post'], '/confirmemail/{path?}', [UtilityController::class, 'confirmemail'])->where('path', '.*')->name('confirmemail.legacy');
Route::match(['get', 'post'], '/cron', [SystemMaintenanceController::class, 'cron'])->middleware('cron.token')->name('cron.legacy');
Route::match(['get', 'post'], '/email-gateway', [TorrentDownloadController::class, 'emailGateway'])->name('email-gateway.legacy');
Route::match(['get', 'post'], '/ok', [UtilityController::class, 'ok'])->name('ok.legacy');

Route::match(['get', 'post'], '/complains', [SupportController::class, 'complains'])->name('complains.legacy');
Route::match(['get', 'post'], '/shoutbox', [ShoutboxController::class, 'shoutbox'])->middleware('throttle:shoutbox')->name('shoutbox.legacy');

Route::match(['get', 'post'], '/bookmark', [TorrentBookmarkController::class, 'bookmark'])->name('bookmark.legacy');
Route::match(['get', 'post'], '/viewfilelist', [TorrentAjaxController::class, 'viewFileList'])->name('viewfilelist.legacy');
Route::match(['get', 'post'], '/viewpeerlist', [TorrentAjaxController::class, 'viewPeerList'])->name('viewpeerlist.legacy');
