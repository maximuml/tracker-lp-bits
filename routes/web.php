<?php

use App\Http\Controllers\Ajax\AttendanceAjaxController as AjaxAttendanceController;
use App\Http\Controllers\Ajax\BenefitAjaxController as AjaxBenefitController;
use App\Http\Controllers\Ajax\HitAndRunAjaxController as AjaxHitAndRunController;
use App\Http\Controllers\Ajax\ModerationAjaxController as AjaxModerationController;
use App\Http\Controllers\Ajax\NotificationAjaxController as AjaxNotificationController;
use App\Http\Controllers\Ajax\OfferAjaxController as AjaxOfferController;
use App\Http\Controllers\Ajax\PasskeyAjaxController as AjaxPasskeyController;
use App\Http\Controllers\Ajax\ShoutboxAjaxController as AjaxShoutboxController;
use App\Http\Controllers\Ajax\TaskAjaxController as AjaxTaskController;
use App\Http\Controllers\Ajax\TorrentApprovalAjaxController as AjaxTorrentApprovalController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\WebController as AuthWebController;
use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\CspReportController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MetricsController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\StaffMessageController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentEditController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UsercpController;
use App\Http\Controllers\UserDetailController;
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
    return redirect('/index');
});

Route::get('/nexus', function () {
    return redirect('/index');
});

Route::get('/health/live', [HealthController::class, 'live'])->name('health.live');
Route::get('/health/ready', [HealthController::class, 'ready'])->name('health.ready');
Route::get('/health/diag', [HealthController::class, 'diag'])->middleware('auth.nexus:nexus-web')->name('health.diag');
Route::get('/health', [HealthController::class, 'live'])->name('health');

// Prometheus-compatible metrics endpoint (T-23: access-controlled)
Route::get('/metrics', [MetricsController::class, 'index'])->middleware(['metrics.access', 'throttle:metrics'])->name('metrics');

// CSP violation reports (no CSRF — browser beacon; no auth — may fire
// before session cookies attach). Throttled to keep flood noise down.
Route::post('/csp-report', [CspReportController::class, 'store'])->middleware('throttle:60,1')->name('csp-report');

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthWebController::class, 'logout'])->name('logout');
// GET /logout must not end the session (logout-CSRF via <img src=/logout>).
// The UI logs out through the POST form; a bare GET just bounces to index.
Route::get('/logout', function () {
    return redirect('/');
});

Route::get('/signup', [RegistrationController::class, 'showSignup'])->name('signup');
Route::post('/signup', [RegistrationController::class, 'signup'])->middleware('throttle:login');
Route::post('/takesignup', [RegistrationController::class, 'signup'])->middleware('throttle:login');

Route::get('/confirm', [RegistrationController::class, 'confirm'])->name('confirm');

Route::get('/confirm_resend', [RegistrationController::class, 'showConfirmResend'])->name('confirm_resend');
Route::post('/confirm_resend', [RegistrationController::class, 'resendConfirmation'])->middleware('throttle:login');

Route::get('/recover', [RecoveryController::class, 'recover'])
    ->middleware('throttle:login')
    ->name('recover');
Route::post('/recover', [RecoveryController::class, 'recoverPost'])
    ->middleware('throttle:login');
Route::post('/recover/reset', [RecoveryController::class, 'resetPassword'])
    ->middleware('throttle:login')
    ->name('recover.reset');

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

Route::get('/forums', [ForumController::class, 'legacy'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('forums.legacy');
Route::post('/forums', [ForumController::class, 'legacyAction'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy', 'reject.get.mutations']);

Route::get('/userdetails', [UserDetailController::class, 'show'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('user.details');

Route::get('/usercp', [UsercpController::class, 'legacy'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy'])
    ->name('usercp.legacy');
Route::post('/usercp', [UsercpController::class, 'legacyAction'])
    ->middleware(['auth.nexus:nexus-web', 'throttle:legacy', 'reject.get.mutations']);

Route::group(['prefix' => 'web', 'middleware' => ['auth.nexus:nexus-web', 'throttle:legacy']], function () {
    Route::post('usercp/theme', [UsercpController::class, 'saveTheme'])
        ->name('usercp.theme');
    Route::post('usercp/logout-all', [AuthWebController::class, 'logoutAllDevices'])
        ->name('usercp.logout-all');
    Route::get('torrent-approval-page', [TorrentController::class, 'approvalPage']);
    Route::get('torrent-approval-logs', [TorrentController::class, 'approvalLogs']);
    Route::post('torrent-approval', [TorrentController::class, 'approval']);
    Route::post('token/add', [TokenController::class, 'addToken']);
    Route::post('token/del', [TokenController::class, 'delToken']);

    // REST endpoints for the actions the page POST dispatchers used to
    // route by `action` param — POST /{friends,news}.php?action=X now
    // 308-redirects here with the same body.
    Route::post('friends/add', [FriendsController::class, 'friendAdd']);
    Route::post('friends/delete', [FriendsController::class, 'friendDelete']);
    Route::post('news/add', [NewsController::class, 'newsAdd']);
    Route::post('news/edit', [NewsController::class, 'newsEdit']);
    Route::post('news/delete', [NewsController::class, 'newsDelete']);
    Route::post('log/chronicle/add', [LogController::class, 'chronicleAddPost']);
    Route::post('log/chronicle/update', [LogController::class, 'chronicleUpdatePost']);
    Route::post('log/chronicle/delete', [LogController::class, 'chronicleDeletePost']);
    Route::post('log/poll/delete', [LogController::class, 'pollDeletePost']);
    Route::post('forums/post', [ForumController::class, 'post']);
    Route::post('forums/movetopic', [ForumController::class, 'moveTopic']);
    Route::post('forums/deletetopic', [ForumController::class, 'deleteTopic']);
    Route::post('forums/deletepost', [ForumController::class, 'deletePost']);
    Route::post('forums/setlocked', [ForumController::class, 'setLocked']);
    Route::post('forums/hltopic', [ForumController::class, 'highlightTopic']);
    Route::post('forums/setsticky', [ForumController::class, 'setSticky']);
    Route::post('usercp/personal', [UsercpController::class, 'savePersonal']);
    Route::post('usercp/forum', [UsercpController::class, 'saveForum']);
    Route::post('usercp/tracker', [UsercpController::class, 'saveTracker']);
    Route::post('usercp/security/confirm', [UsercpController::class, 'confirmSecurity']);
    Route::post('messages/send', [MessageController::class, 'send']);
    Route::post('messages/delete/{type}', [MessageController::class, 'deleteTyped'])->whereIn('type', ['in', 'out']);
    Route::post('messages/move-or-delete', [MessageController::class, 'moveOrDelete']);
    Route::post('messages/mailboxes', [MessageController::class, 'editMailboxes']);
    Route::post('messages/delete', [MessageController::class, 'deleteMailboxMessage']);
    Route::post('staffmess/send', [StaffMessageController::class, 'sendStaffMessage']);
    Route::post('contactstaff/send', [StaffMessageController::class, 'sendContactStaff']);
    Route::post('offers/create', [OfferController::class, 'store']);
    Route::post('offers/allow', [OfferController::class, 'allow']);
    Route::post('offers/finish', [OfferController::class, 'finish']);
    Route::post('offers/delete', [OfferController::class, 'destroy']);
    Route::post('offers/edit', [OfferController::class, 'update']);
    Route::post('mybonus/exchange', [MyController::class, 'exchangeBonus']);

    // REST endpoints for the actions the /ajax dispatcher used to route by
    // `action` string — POST /ajax {action: X} now 308-redirects here with
    // the same {action, params} body (FormRequests flatten the envelope).
    Route::middleware('throttle:ajax')->group(function () {
        Route::post('attendance/retroactive', [AjaxAttendanceController::class, 'retroactive']);
        Route::post('users/leech-warn/remove', [AjaxModerationController::class, 'removeLeechWarn']);
        // POST-only like the old /ajax route (GET /ajax was rejected) —
        // the 308 redirect replays the original POST body unchanged.
        Route::post('offers/show', [AjaxOfferController::class, 'show']);
        Route::post('torrents/approval-modal', [AjaxTorrentApprovalController::class, 'modal']);
        Route::post('hit-and-runs/remove', [AjaxHitAndRunController::class, 'remove']);
        Route::post('benefits/consume', [AjaxBenefitController::class, 'consume']);
        Route::post('tasks/claim', [AjaxTaskController::class, 'claim']);
        Route::post('notifications/feed', [AjaxNotificationController::class, 'feed']);
        Route::post('shoutbox/clear', [AjaxShoutboxController::class, 'clear']);
        Route::post('shoutbox/post', [AjaxShoutboxController::class, 'post']);
        Route::post('shoutbox/edit', [AjaxShoutboxController::class, 'edit']);
        Route::post('shoutbox/delete', [AjaxShoutboxController::class, 'delete']);
        Route::post('shoutbox/react', [AjaxShoutboxController::class, 'react']);
        Route::post('passkey/create-args', [AjaxPasskeyController::class, 'createArgs']);
        Route::post('passkey/create', [AjaxPasskeyController::class, 'processCreate']);
        Route::post('passkey/list', [AjaxPasskeyController::class, 'list']);
        Route::post('passkey/delete', [AjaxPasskeyController::class, 'delete']);
    });
});

// Passkey assertion endpoints called by the login page — guest-facing,
// so they live outside the auth.nexus group. The old /ajax dispatcher
// skipped requireLoginFromContext for exactly these two actions.
Route::group(['prefix' => 'web', 'middleware' => ['throttle:ajax']], function () {
    Route::post('passkey/get-args', [AjaxPasskeyController::class, 'getArgs']);
    Route::post('passkey/get', [AjaxPasskeyController::class, 'processGet']);
});

// Complaint channel actions — guest-facing by design (appeals from banned
// accounts); per-action gates inside SupportController: captcha + per-IP
// locks on 'new', secret-uuid match on guest 'reply', staff-only toggles.
// POST /complains.php?action=X 308-redirects here with the same body.
Route::group(['prefix' => 'web', 'middleware' => ['throttle:legacy']], function () {
    Route::post('complains/new', [SupportController::class, 'complainNewPost']);
    Route::post('complains/reply', [SupportController::class, 'complainReplyPost']);
    Route::post('complains/answered', [SupportController::class, 'complainAnsweredPost']);
    Route::post('complains/unanswered', [SupportController::class, 'complainUnansweredPost']);
});

// Passkey login v2 — fixed route with HMAC-SHA256, nonce replay protection,
// and key rotation. Registered unconditionally so `php artisan route:cache`
// keeps it; the feature flag and the enrollment deadline are enforced
// per-request by the `passkey.v2` middleware — settings live in the
// database and may change after routes are cached.
Route::post('/auth/passkey', [AuthenticateController::class, 'passkeyLoginV2'])
    ->middleware(['passkey.v2', 'throttle:passkey-login']);

// Legacy passkey login uses an admin-configured secret URI (`login_secret`)
// which cannot be baked into a static route — a secret changed after
// `route:cache` would never match. A POST catch-all registered last in
// RouteServiceProvider dispatches the configured secret at runtime to
// AuthenticateController::legacyPasskeyFallback.
