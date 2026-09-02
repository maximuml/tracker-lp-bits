<?php

use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\WebController as AuthWebController;
use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentEditController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UsercpController;
use App\Http\Controllers\UserDetailController;
use App\Support\Config\SiteConfig;
use App\Support\Environment;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('index.php');
});

Route::get('/nexus', function () {
    return redirect('index.php');
});

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/health', [HealthController::class, 'live'])->name('health');

// Prometheus-compatible metrics endpoint (no auth, no throttling)
Route::get('/metrics', [MetricsController::class, 'index'])->name('metrics');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::get('/signup', [RegistrationController::class, 'showSignup'])->name('signup');
Route::post('/signup', [RegistrationController::class, 'signup'])->middleware('throttle:login');
Route::post('/takesignup', [RegistrationController::class, 'signup'])->middleware('throttle:login');

Route::get('/confirm', [RegistrationController::class, 'confirm'])->name('confirm');

Route::get('/confirm_resend', [RegistrationController::class, 'showConfirmResend'])->name('confirm_resend');
Route::post('/confirm_resend', [RegistrationController::class, 'resendConfirmation'])->middleware('throttle:login');

Route::match(['get', 'post'], '/recover', [RecoveryController::class, 'recover'])
    ->middleware('throttle:login')
    ->name('recover');

Route::get('/error', [ToolController::class, 'error']);

Route::post('/takeupload', [TorrentUploadController::class, 'legacyStore'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:upload'])
    ->name('torrents.legacy-store');

Route::get('/edit', [TorrentEditController::class, 'legacy'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('torrents.legacy-edit');

Route::post('/takeedit', [TorrentEditController::class, 'legacyUpdate'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('torrents.legacy-update');

Route::get('/download', [TorrentDownloadController::class, 'download'])
    ->middleware('throttle:download')
    ->name('torrents.download');

Route::middleware(['web', 'locale', 'throttle:legacy'])->group(base_path('routes/legacy/public.php'));

Route::group(['middleware' => ['auth.nexus:nexus-web', 'throttle:legacy']], base_path('routes/legacy/auth.php'));

Route::match(['get', 'post'], '/forums', [ForumController::class, 'legacy'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('forums.legacy');

Route::get('/userdetails', [UserDetailController::class, 'show'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('user.details');

Route::match(['get', 'post'], '/usercp', [UsercpController::class, 'legacy'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('usercp.legacy');

Route::group(['prefix' => 'web', 'middleware' => ['auth.nexus:nexus-web', 'throttle:legacy']], function () {
    Route::get('torrent-approval-page', [TorrentController::class, 'approvalPage']);
    Route::get('torrent-approval-logs', [TorrentController::class, 'approvalLogs']);
    Route::post('torrent-approval', [TorrentController::class, 'approval']);
    Route::post('token/add', [TokenController::class, 'addToken']);
    Route::post('token/del', [TokenController::class, 'delToken']);
});

if (! Environment::isConsole()) {
    $passkeyLoginUri = SiteConfig::current()->security->loginSecret();
    if (! empty($passkeyLoginUri) && SiteConfig::current()->security->loginType() === 'passkey') {
        Route::post($passkeyLoginUri, [AuthenticateController::class, 'passkeyLogin']);
    }
}
