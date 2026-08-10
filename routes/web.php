<?php

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\WebController as AuthWebController;
use App\Http\Controllers\BitbucketUploadController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\TorrentActionController;
use App\Http\Controllers\TorrentDetailsController;
use App\Http\Controllers\WebCommentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentEditController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\TorrentListingController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\UsercpController;
use App\Http\Controllers\ToptenController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\IndexController;
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

Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('health');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login'])->middleware('throttle:login');
Route::get('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::get('/signup', [RegistrationController::class, 'showSignup'])->name('signup');
Route::post('/signup', [RegistrationController::class, 'signup'])->middleware('throttle:login');
Route::post('/takesignup', [RegistrationController::class, 'signup'])->middleware('throttle:login');

Route::get('/confirm', [RegistrationController::class, 'confirm'])->name('confirm');

Route::get('/confirm_resend', [RegistrationController::class, 'showConfirmResend'])->name('confirm_resend');
Route::post('/confirm_resend', [RegistrationController::class, 'resendConfirmation'])->middleware('throttle:login');

Route::match(['get', 'post'], '/recover', [RecoveryController::class, 'recover'])
    ->middleware('throttle:login')
    ->name('recover');

Route::get("/error", [\App\Http\Controllers\ToolController::class, "error"]);

Route::post('/takeupload', [TorrentUploadController::class, 'legacyStore'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:upload'])
    ->name('torrents.legacy-store');

Route::get('/edit', [TorrentEditController::class, 'legacy'])
    ->middleware('auth.nexus:nexus-web')
    ->name('torrents.legacy-edit');

Route::post('/takeedit', [TorrentEditController::class, 'legacyUpdate'])
    ->middleware('auth.nexus:nexus-web')
    ->name('torrents.legacy-update');

Route::get('/download', [TorrentDownloadController::class, 'download'])
    ->name('torrents.download');


Route::middleware(['web', 'locale'])->group(base_path('routes/legacy/public.php'));

Route::group(['middleware' => ['auth.nexus:nexus-web']], base_path('routes/legacy/auth.php'));

Route::match(['get', 'post'], '/forums', [ForumController::class, 'legacy'])
    ->middleware('auth.nexus:nexus-web')
    ->name('forums.legacy');

Route::get('/userdetails', [UserDetailController::class, 'show'])
    ->middleware('auth.nexus:nexus-web')
    ->name('user.details');

Route::match(['get', 'post'], '/usercp', [UsercpController::class, 'legacy'])
    ->middleware('auth.nexus:nexus-web')
    ->name('usercp.legacy');

Route::group(['prefix' => 'web', 'middleware' => ['auth.nexus:nexus-web']], function () {
    Route::get('torrent-approval-page', [\App\Http\Controllers\TorrentController::class, 'approvalPage']);
    Route::get('torrent-approval-logs', [\App\Http\Controllers\TorrentController::class, 'approvalLogs']);
    Route::post('torrent-approval', [\App\Http\Controllers\TorrentController::class, 'approval']);
    Route::post('token/add', [\App\Http\Controllers\TokenController::class, 'addToken']);
    Route::post('token/del', [\App\Http\Controllers\TokenController::class, 'delToken']);
});

if (!isRunningInConsole()) {
    $passkeyLoginUri = \App\Support\Config\SiteConfig::current()->security->loginSecret();
    if (!empty($passkeyLoginUri) && \App\Support\Config\SiteConfig::current()->security->loginType() === 'passkey') {
        Route::get("$passkeyLoginUri/{passkey}", [\App\Http\Controllers\AuthenticateController::class, 'passkeyLogin']);
    }
}

Route::group(['prefix' => 'oauth'], function () {
    Route::get("user-info", [\App\Http\Controllers\OauthController::class, 'userInfo'])->name("oauth.user_info")->middleware('auth:api');
    Route::get('redirect/{uuid}', [\App\Http\Controllers\OauthController::class, 'redirect']);
    Route::get('callback/{uuid}', [\App\Http\Controllers\OauthController::class, 'callback']);
});