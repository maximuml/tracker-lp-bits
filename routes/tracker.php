<?php

use App\Http\Controllers\AnnounceController;
use Illuminate\Support\Facades\Route;

Route::any('/announce.php', [AnnounceController::class, 'announce']);
