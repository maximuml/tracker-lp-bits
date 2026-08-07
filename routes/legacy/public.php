<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\InfoController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TorrentActionController;
use App\Http\Controllers\UtilityController;

Route::match(['get', 'post'], '/aboutnexus', [InfoController::class, 'aboutNexus'])->name('aboutnexus.legacy');
Route::match(['get', 'post'], '/rules', [InfoController::class, 'rules'])->name('rules.legacy');
Route::match(['get', 'post'], '/useragreement', [InfoController::class, 'userAgreement'])->name('useragreement.legacy');
Route::match(['get', 'post'], '/faq', [InfoController::class, 'faq'])->name('faq.legacy');
Route::match(['get', 'post'], '/donate', [InfoController::class, 'donate'])->name('donate.legacy');
Route::match(['get', 'post'], '/getusertorrentlistajax', [TorrentActionController::class, 'getUserTorrentListAjax'])->name('getusertorrentlistajax.legacy');
Route::match(['get', 'post'], '/searchsuggest', [TorrentActionController::class, 'searchSuggest'])->name('searchsuggest.legacy');
Route::match(['get', 'post'], '/ajax', [UtilityController::class, 'ajax'])->name('ajax.legacy');

Route::match(['get', 'post'], '/image', [UtilityController::class, 'image'])->name('image.legacy');
Route::match(['get', 'post'], '/shoutbox_sse', [ShoutboxController::class, 'shoutboxSse'])->name('shoutbox_sse.legacy');

Route::match(['get', 'post'], '/torrentrss', [TorrentActionController::class, 'torrentrss'])->name('torrentrss.legacy');

Route::match(['get', 'post'], '/page', [UtilityController::class, 'page'])->name('page.legacy');
Route::match(['get', 'post'], '/tags', [UtilityController::class, 'tags'])->name('tags.legacy');
Route::match(['get', 'post'], '/suggest', [UtilityController::class, 'suggest'])->name('suggest.legacy');
Route::match(['get', 'post'], '/opensearch', [UtilityController::class, 'opensearch'])->name('opensearch.legacy');

Route::match(['get', 'post'], '/confirmemail', [UtilityController::class, 'confirmemail'])->name('confirmemail.legacy');
Route::match(['get', 'post'], '/cron', [SystemController::class, 'cron'])->name('cron.legacy');
Route::match(['get', 'post'], '/email-gateway', [TorrentActionController::class, 'emailGateway'])->name('email-gateway.legacy');
Route::match(['get', 'post'], '/ok', [UtilityController::class, 'ok'])->name('ok.legacy');

Route::match(['get', 'post'], '/complains', [SupportController::class, 'complains'])->name('complains.legacy');
Route::match(['get', 'post'], '/shoutbox', [ShoutboxController::class, 'shoutbox'])->name('shoutbox.legacy');

Route::match(['get', 'post'], '/bookmark', [TorrentActionController::class, 'bookmark'])->name('bookmark.legacy');
Route::match(['get', 'post'], '/viewfilelist', [TorrentActionController::class, 'viewFileList'])->name('viewfilelist.legacy');
Route::match(['get', 'post'], '/viewpeerlist', [TorrentActionController::class, 'viewPeerList'])->name('viewpeerlist.legacy');