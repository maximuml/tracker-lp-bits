<?php

use App\Http\Controllers\Auth\RegistrationController;
use App\Http\Controllers\Auth\RecoveryController;
use App\Http\Controllers\Auth\WebController as AuthWebController;
use App\Http\Controllers\BitbucketUploadController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\TorrentDetailsController;
use App\Http\Controllers\WebCommentController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentEditController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\TorrentListingController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UserDetailController;
use App\Http\Controllers\UsercpController;
use App\Http\Controllers\ToptenController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\LegacyPagesController;
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

Route::get('/login', [AuthWebController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthWebController::class, 'login']);
Route::get('/logout', [AuthWebController::class, 'logout'])->name('logout');

Route::get('/signup', [RegistrationController::class, 'showSignup'])->name('signup');
Route::post('/signup', [RegistrationController::class, 'signup']);
Route::post('/takesignup', [RegistrationController::class, 'signup']);

Route::get('/confirm', [RegistrationController::class, 'confirm'])->name('confirm');

Route::get('/confirm_resend', [RegistrationController::class, 'showConfirmResend'])->name('confirm_resend');
Route::post('/confirm_resend', [RegistrationController::class, 'resendConfirmation']);

Route::match(['get', 'post'], '/recover', [RecoveryController::class, 'recover'])->name('recover');

Route::get("/error", [\App\Http\Controllers\ToolController::class, "error"]);

Route::post('/takeupload', [TorrentUploadController::class, 'legacyStore'])
    ->middleware('auth.nexus:nexus-web')
    ->name('torrents.legacy-store');

Route::get('/edit', [TorrentEditController::class, 'legacy'])
    ->middleware('auth.nexus:nexus-web')
    ->name('torrents.legacy-edit');

Route::post('/takeedit', [TorrentEditController::class, 'legacyUpdate'])
    ->middleware('auth.nexus:nexus-web')
    ->name('torrents.legacy-update');

Route::get('/download', [TorrentDownloadController::class, 'download'])
    ->name('torrents.download');

Route::match(['get', 'post'], '/aboutnexus', [LegacyPagesController::class, 'aboutNexus'])->name('aboutnexus.legacy');
Route::match(['get', 'post'], '/rules', [LegacyPagesController::class, 'rules'])->name('rules.legacy');
Route::match(['get', 'post'], '/useragreement', [LegacyPagesController::class, 'userAgreement'])->name('useragreement.legacy');
Route::match(['get', 'post'], '/faq', [LegacyPagesController::class, 'faq'])->name('faq.legacy');
Route::match(['get', 'post'], '/donate', [LegacyPagesController::class, 'donate'])->name('donate.legacy');

Route::group(['middleware' => ['auth.nexus:nexus-web']], function () {
    Route::get('/upload', [TorrentUploadController::class, 'create'])->name('torrents.upload');
    Route::get('/bitbucket-upload', [BitbucketUploadController::class, 'create'])->name('bitbucket.upload');
    Route::post('/bitbucket-upload', [BitbucketUploadController::class, 'store']);
    Route::match(['get', 'post'], '/offers', [OfferController::class, 'legacy'])->name('offers.legacy');
    Route::get('/torrents', [TorrentListingController::class, 'index'])->name('torrents.index');
    Route::get('/special', [TorrentListingController::class, 'index'])->name('torrents.special');
    Route::get('/details/{id}', [TorrentDetailsController::class, 'show'])->name('torrent.details');
    Route::match(['get', 'post'], '/mybonus', [MyController::class, 'bonus'])->name('my.bonus');
    Route::get('/myhr', [MyController::class, 'hr'])->name('my.hr');
    Route::get('/topten', [ToptenController::class, 'legacy'])->name('topten.legacy');
    Route::get('/log', [LogController::class, 'legacy'])->name('log.legacy');
    Route::match(['get', 'post'], '/index', [IndexController::class, 'legacy'])->name('index.legacy');
    Route::match(['get', 'post'], '/friends', [LegacyPagesController::class, 'friends'])->name('friends.legacy');
    Route::match(['get', 'post'], '/messages', [LegacyPagesController::class, 'messages'])->name('messages.legacy');
    Route::match(['get', 'post'], '/getrss', [LegacyPagesController::class, 'getrss'])->name('getrss.legacy');
    Route::match(['get', 'post'], '/sendmessage', [LegacyPagesController::class, 'sendmessage'])->name('sendmessage.legacy');
    Route::match(['get', 'post'], '/userhistory', [LegacyPagesController::class, 'userhistory'])->name('userhistory.legacy');
    Route::match(['get', 'post'], '/invite', [LegacyPagesController::class, 'invite'])->name('invite.legacy');
    Route::match(['get', 'post'], '/news', [LegacyPagesController::class, 'news'])->name('news.legacy');
    Route::match(['get', 'post'], '/makepoll', [LegacyPagesController::class, 'makepoll'])->name('makepoll.legacy');
    Route::match(['get', 'post'], '/polloverview', [LegacyPagesController::class, 'polloverview'])->name('polloverview.legacy');
    Route::match(['get', 'post'], '/attendance', [LegacyPagesController::class, 'attendance'])->name('attendance.legacy');
    Route::match(['get', 'post'], '/takemessage', [LegacyPagesController::class, 'takeMessage'])->name('takemessage.legacy');
    Route::match(['get', 'post'], '/deletemessage', [LegacyPagesController::class, 'deletemessage'])->name('deletemessage.legacy');
    Route::match(['get', 'post'], '/report', [LegacyPagesController::class, 'report'])->name('report.legacy');
    Route::match(['get', 'post'], '/reports', [LegacyPagesController::class, 'reports'])->name('reports.legacy');
    Route::match(['get', 'post'], '/bans', [LegacyPagesController::class, 'bans'])->name('bans.legacy');
    Route::match(['get', 'post'], '/cheaterbox', [LegacyPagesController::class, 'cheaterbox'])->name('cheaterbox.legacy');
    Route::match(['get', 'post'], '/cheaters', [LegacyPagesController::class, 'cheaters'])->name('cheaters.legacy');
    Route::match(['get', 'post'], '/iphistory', [LegacyPagesController::class, 'iphistory'])->name('iphistory.legacy');
    Route::match(['get', 'post'], '/ipcheck', [LegacyPagesController::class, 'ipcheck'])->name('ipcheck.legacy');
    Route::match(['get', 'post'], '/ipsearch', [LegacyPagesController::class, 'ipsearch'])->name('ipsearch.legacy');
    Route::match(['get', 'post'], '/modtask', [LegacyPagesController::class, 'modtask'])->name('modtask.legacy');
    Route::match(['get', 'post'], '/staff', [LegacyPagesController::class, 'staff'])->name('staff.legacy');
    Route::match(['get', 'post'], '/staffbox', [LegacyPagesController::class, 'staffbox'])->name('staffbox.legacy');
    Route::match(['get', 'post'], '/staffmess', [LegacyPagesController::class, 'staffmess'])->name('staffmess.legacy');
    Route::match(['get', 'post'], '/takestaffmess', [LegacyPagesController::class, 'takeStaffmess'])->name('takestaffmess.legacy');
    Route::match(['get', 'post'], '/contactstaff', [LegacyPagesController::class, 'contactstaff'])->name('contactstaff.legacy');
    Route::match(['get', 'post'], '/takecontact', [LegacyPagesController::class, 'takecontact'])->name('takecontact.legacy');
    Route::match(['get', 'post'], '/modrules', [LegacyPagesController::class, 'modrules'])->name('modrules.legacy');
    Route::match(['get', 'post'], '/donorlist', [LegacyPagesController::class, 'donorlist'])->name('donorlist.legacy');
    Route::match(['get', 'post'], '/stats', [LegacyPagesController::class, 'stats'])->name('stats.legacy');
    Route::match(['get', 'post'], '/warned', [LegacyPagesController::class, 'warned'])->name('warned.legacy');
    Route::match(['get', 'post'], '/nowarn', [LegacyPagesController::class, 'nowarn'])->name('nowarn.legacy');
    Route::match(['get', 'post'], '/allagents', [LegacyPagesController::class, 'allagents'])->name('allagents.legacy');
    Route::match(['get', 'post'], '/checkuser', [LegacyPagesController::class, 'checkuser'])->name('checkuser.legacy');
    Route::match(['get', 'post'], '/takeconfirm', [LegacyPagesController::class, 'takeconfirm'])->name('takeconfirm.legacy');
    Route::match(['get', 'post'], '/user-ban-log', [LegacyPagesController::class, 'userBanLog'])->name('user-ban-log.legacy');
    Route::match(['get', 'post'], '/torrent_info', [LegacyPagesController::class, 'torrentInfo'])->name('torrent_info.legacy');
    Route::match(['get', 'post'], '/viewsnatches', [LegacyPagesController::class, 'viewSnatches'])->name('viewsnatches.legacy');
    Route::match(['get', 'post'], '/takeflush', [LegacyPagesController::class, 'takeFlush'])->name('takeflush.legacy');
    Route::match(['get', 'post'], '/takereseed', [LegacyPagesController::class, 'takeReseed'])->name('takereseed.legacy');
    Route::match(['get', 'post'], '/clearcache', [LegacyPagesController::class, 'clearCache'])->name('clearcache.legacy');
    Route::match(['get', 'post'], '/fastdelete', [LegacyPagesController::class, 'fastDelete'])->name('fastdelete.legacy');
    Route::match(['get', 'post'], '/donated', [LegacyPagesController::class, 'donated'])->name('donated.legacy');
    Route::match(['get', 'post'], '/faqmanage', [LegacyPagesController::class, 'faqManage'])->name('faqmanage.legacy');
    Route::match(['get', 'post'], '/faqactions', [LegacyPagesController::class, 'faqActions'])->name('faqactions.legacy');
    Route::get('/comment/add', [WebCommentController::class, 'create']);
    Route::post('/comment', [WebCommentController::class, 'store']);
    Route::get('/comment/{commentId}/edit', [WebCommentController::class, 'edit']);
    Route::post('/comment/{commentId}/edit', [WebCommentController::class, 'update']);
    Route::get('/comment/{commentId}/delete', [WebCommentController::class, 'destroy']);
    Route::get('/comment/{commentId}/original', [WebCommentController::class, 'original']);
});

Route::match(['get', 'post'], '/bookmark', [LegacyPagesController::class, 'bookmark'])->name('bookmark.legacy');
Route::match(['get', 'post'], '/viewfilelist', [LegacyPagesController::class, 'viewFileList'])->name('viewfilelist.legacy');
Route::match(['get', 'post'], '/viewpeerlist', [LegacyPagesController::class, 'viewPeerList'])->name('viewpeerlist.legacy');

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
    $passkeyLoginUri = get_setting('security.login_secret');
    if (!empty($passkeyLoginUri) && get_setting('security.login_type') == 'passkey') {
        Route::get("$passkeyLoginUri/{passkey}", [\App\Http\Controllers\AuthenticateController::class, 'passkeyLogin']);
    }
}

Route::group(['prefix' => 'oauth'], function () {
    Route::get("user-info", [\App\Http\Controllers\OauthController::class, 'userInfo'])->name("oauth.user_info")->middleware('auth:api');
    Route::get('redirect/{uuid}', [\App\Http\Controllers\OauthController::class, 'redirect']);
    Route::get('callback/{uuid}', [\App\Http\Controllers\OauthController::class, 'callback']);
});
