<?php

use App\Http\Controllers\AnnounceController;
use App\Http\Controllers\ScrapeController;
use Illuminate\Support\Facades\Route;

Route::any('/announce', [AnnounceController::class, 'announce']);
Route::any('/announce.php', [AnnounceController::class, 'announce']);

Route::any('/scrape', [ScrapeController::class, 'scrape']);
Route::any('/scrape.php', [ScrapeController::class, 'scrape']);
