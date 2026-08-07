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
use App\Http\Controllers\LegacyPagesController;
use App\Http\Controllers\ManagementController;
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
Route::match(['get', 'post'], '/getusertorrentlistajax', [LegacyPagesController::class, 'getUserTorrentListAjax'])->name('getusertorrentlistajax.legacy');
Route::match(['get', 'post'], '/searchsuggest', [LegacyPagesController::class, 'searchSuggest'])->name('searchsuggest.legacy');
Route::match(['get', 'post'], '/ajax', [LegacyPagesController::class, 'ajax'])->name('ajax.legacy');

Route::match(['get', 'post'], '/image', [LegacyPagesController::class, 'image'])->name('image.legacy');
Route::match(['get', 'post'], '/shoutbox_sse', [ShoutboxController::class, 'shoutboxSse'])->name('shoutbox_sse.legacy');

Route::match(['get', 'post'], '/torrentrss', [LegacyPagesController::class, 'torrentrss'])->name('torrentrss.legacy');

Route::match(['get', 'post'], '/page', [LegacyPagesController::class, 'page'])->name('page.legacy');
Route::match(['get', 'post'], '/tags', [LegacyPagesController::class, 'tags'])->name('tags.legacy');
Route::match(['get', 'post'], '/suggest', [LegacyPagesController::class, 'suggest'])->name('suggest.legacy');
Route::match(['get', 'post'], '/opensearch', [LegacyPagesController::class, 'opensearch'])->name('opensearch.legacy');

Route::match(['get', 'post'], '/confirmemail', [LegacyPagesController::class, 'confirmemail'])->name('confirmemail.legacy');
Route::match(['get', 'post'], '/cron', [ManagementController::class, 'cron'])->name('cron.legacy');
Route::match(['get', 'post'], '/email-gateway', [LegacyPagesController::class, 'emailGateway'])->name('email-gateway.legacy');
Route::match(['get', 'post'], '/ok', [LegacyPagesController::class, 'ok'])->name('ok.legacy');

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
    Route::match(['get', 'post'], '/friends', [FriendsController::class, 'friends'])->name('friends.legacy');
    Route::match(['get', 'post'], '/messages', [MessageController::class, 'messages'])->name('messages.legacy');
    Route::match(['get', 'post'], '/getrss', [LegacyPagesController::class, 'getrss'])->name('getrss.legacy');
    Route::match(['get', 'post'], '/sendmessage', [MessageController::class, 'sendmessage'])->name('sendmessage.legacy');
    Route::match(['get', 'post'], '/userhistory', [LegacyPagesController::class, 'userhistory'])->name('userhistory.legacy');
    Route::match(['get', 'post'], '/invite', [LegacyPagesController::class, 'invite'])->name('invite.legacy');
    Route::match(['get', 'post'], '/news', [LegacyPagesController::class, 'news'])->name('news.legacy');
    Route::match(['get', 'post'], '/makepoll', [LegacyPagesController::class, 'makepoll'])->name('makepoll.legacy');
    Route::match(['get', 'post'], '/polloverview', [LegacyPagesController::class, 'polloverview'])->name('polloverview.legacy');
    Route::match(['get', 'post'], '/attendance', [LegacyPagesController::class, 'attendance'])->name('attendance.legacy');
    Route::match(['get', 'post'], '/takemessage', [MessageController::class, 'takeMessage'])->name('takemessage.legacy');
    Route::match(['get', 'post'], '/deletemessage', [MessageController::class, 'deletemessage'])->name('deletemessage.legacy');
    Route::match(['get', 'post'], '/report', [ManagementController::class, 'report'])->name('report.legacy');
    Route::match(['get', 'post'], '/reports', [ManagementController::class, 'reports'])->name('reports.legacy');
    Route::match(['get', 'post'], '/bans', [ManagementController::class, 'bans'])->name('bans.legacy');
    Route::match(['get', 'post'], '/cheaterbox', [ManagementController::class, 'cheaterbox'])->name('cheaterbox.legacy');
    Route::match(['get', 'post'], '/cheaters', [ManagementController::class, 'cheaters'])->name('cheaters.legacy');
    Route::match(['get', 'post'], '/iphistory', [ManagementController::class, 'iphistory'])->name('iphistory.legacy');
    Route::match(['get', 'post'], '/ipcheck', [ManagementController::class, 'ipcheck'])->name('ipcheck.legacy');
    Route::match(['get', 'post'], '/ipsearch', [ManagementController::class, 'ipsearch'])->name('ipsearch.legacy');
    Route::match(['get', 'post'], '/modtask', [ManagementController::class, 'modtask'])->name('modtask.legacy');
    Route::match(['get', 'post'], '/staff', [ManagementController::class, 'staff'])->name('staff.legacy');
    Route::match(['get', 'post'], '/staffbox', [ManagementController::class, 'staffbox'])->name('staffbox.legacy');
    Route::match(['get', 'post'], '/staffmess', [ManagementController::class, 'staffmess'])->name('staffmess.legacy');
    Route::match(['get', 'post'], '/takestaffmess', [ManagementController::class, 'takeStaffmess'])->name('takestaffmess.legacy');
    Route::match(['get', 'post'], '/contactstaff', [ManagementController::class, 'contactstaff'])->name('contactstaff.legacy');
    Route::match(['get', 'post'], '/takecontact', [ManagementController::class, 'takecontact'])->name('takecontact.legacy');
    Route::match(['get', 'post'], '/modrules', [ManagementController::class, 'modrules'])->name('modrules.legacy');
    Route::match(['get', 'post'], '/donorlist', [ManagementController::class, 'donorlist'])->name('donorlist.legacy');
    Route::match(['get', 'post'], '/stats', [ManagementController::class, 'stats'])->name('stats.legacy');
    Route::match(['get', 'post'], '/warned', [ManagementController::class, 'warned'])->name('warned.legacy');
    Route::match(['get', 'post'], '/nowarn', [ManagementController::class, 'nowarn'])->name('nowarn.legacy');
    Route::match(['get', 'post'], '/allagents', [ManagementController::class, 'allagents'])->name('allagents.legacy');

    Route::match(['get', 'post'], '/checkuser', [ManagementController::class, 'checkuser'])->name('checkuser.legacy');
    Route::match(['get', 'post'], '/takeconfirm', [ManagementController::class, 'takeconfirm'])->name('takeconfirm.legacy');
    Route::match(['get', 'post'], '/user-ban-log', [ManagementController::class, 'userBanLog'])->name('user-ban-log.legacy');
    Route::match(['get', 'post'], '/torrent_info', [LegacyPagesController::class, 'torrentInfo'])->name('torrent_info.legacy');
    Route::match(['get', 'post'], '/viewsnatches', [LegacyPagesController::class, 'viewSnatches'])->name('viewsnatches.legacy');
    Route::match(['get', 'post'], '/takeflush', [LegacyPagesController::class, 'takeFlush'])->name('takeflush.legacy');
    Route::match(['get', 'post'], '/takereseed', [LegacyPagesController::class, 'takeReseed'])->name('takereseed.legacy');
    Route::match(['get', 'post'], '/clearcache', [ManagementController::class, 'clearCache'])->name('clearcache.legacy');
    Route::match(['get', 'post'], '/fastdelete', [LegacyPagesController::class, 'fastDelete'])->name('fastdelete.legacy');
    Route::match(['get', 'post'], '/donated', [LegacyPagesController::class, 'donated'])->name('donated.legacy');
    Route::match(['get', 'post'], '/faqmanage', [LegacyPagesController::class, 'faqManage'])->name('faqmanage.legacy');
    Route::match(['get', 'post'], '/faqactions', [LegacyPagesController::class, 'faqActions'])->name('faqactions.legacy');
    Route::match(['get', 'post'], '/search', [LegacyPagesController::class, 'search'])->name('search.legacy');
    Route::match(['get', 'post'], '/usersearch', [LegacyPagesController::class, 'usersearch'])->name('usersearch.legacy');
    Route::match(['get', 'post'], '/autocomplete_torrents', [LegacyPagesController::class, 'autocompleteTorrents'])->name('autocomplete_torrents.legacy');
    Route::get('/comment/add', [WebCommentController::class, 'create']);
    Route::post('/comment', [WebCommentController::class, 'store']);
    Route::get('/comment/{commentId}/edit', [WebCommentController::class, 'edit']);
    Route::post('/comment/{commentId}/edit', [WebCommentController::class, 'update']);
    Route::get('/comment/{commentId}/delete', [WebCommentController::class, 'destroy']);
    Route::get('/comment/{commentId}/original', [WebCommentController::class, 'original']);
    Route::match(['get', 'post'], '/catmanage', [ManagementController::class, 'catmanage'])->name('catmanage.legacy');
    Route::match(['get', 'post'], '/forummanage', [ForumController::class, 'forummanage'])->name('forummanage.legacy');
    Route::match(['get', 'post'], '/moforums', [ForumController::class, 'moforums'])->name('moforums.legacy');
    Route::match(['get', 'post'], '/fields', [ManagementController::class, 'fields'])->name('fields.legacy');
    Route::match(['get', 'post'], '/formats', [ManagementController::class, 'formats'])->name('formats.legacy');
    Route::match(['get', 'post'], '/videoformats', [ManagementController::class, 'videoformats'])->name('videoformats.legacy');
    Route::match(['get', 'post'], '/attachment', [LegacyPagesController::class, 'attachment'])->name('attachment.legacy');
    Route::match(['get', 'post'], '/getattachment', [LegacyPagesController::class, 'getattachment'])->name('getattachment.legacy');
    Route::match(['get', 'post'], '/shoutbox_history', [ShoutboxController::class, 'shoutboxHistory'])->name('shoutbox_history.legacy');
    Route::match(['get', 'post'], '/latestcomments', [ForumController::class, 'latestcomments'])->name('latestcomments.legacy');
    Route::match(['get', 'post'], '/bonus-log', [ManagementController::class, 'bonusLog'])->name('bonus-log.legacy');
    Route::match(['get', 'post'], '/medal', [ManagementController::class, 'medal'])->name('medal.legacy');
    Route::match(['get', 'post'], '/task', [ManagementController::class, 'task'])->name('task.legacy');
    Route::match(['get', 'post'], '/uploaders', [ManagementController::class, 'uploaders'])->name('uploaders.legacy');
    Route::match(['get', 'post'], '/settings', [ManagementController::class, 'settings'])->name('settings.legacy');
    Route::match(['get', 'post'], '/freeleech', [ManagementController::class, 'freeleech'])->name('freeleech.legacy');
    Route::match(['get', 'post'], '/magic', [ManagementController::class, 'magic'])->name('magic.legacy');
    Route::match(['get', 'post'], '/delacctadmin', [ManagementController::class, 'delacctadmin'])->name('delacctadmin.legacy');
    Route::match(['get', 'post'], '/deletedisabled', [ManagementController::class, 'deletedisabled'])->name('deletedisabled.legacy');
    Route::match(['get', 'post'], '/massmail', [ManagementController::class, 'massmail'])->name('massmail.legacy');
    Route::match(['get', 'post'], '/takeamountupload', [ManagementController::class, 'takeamountupload'])->name('takeamountupload.legacy');
    Route::match(['get', 'post'], '/takeinvite', [ManagementController::class, 'takeinvite'])->name('takeinvite.legacy');
    Route::match(['get', 'post'], '/takeupdate', [ManagementController::class, 'takeupdate'])->name('takeupdate.legacy');
    Route::match(['get', 'post'], '/users', [ManagementController::class, 'users'])->name('users.legacy');
    Route::match(['get', 'post'], '/staffpanel', [ManagementController::class, 'staffpanel'])->name('staffpanel.legacy');
    Route::match(['get', 'post'], '/docleanup', [ManagementController::class, 'docleanup'])->name('docleanup.legacy');
    Route::match(['get', 'post'], '/location', [ManagementController::class, 'location'])->name('location.legacy');
    Route::match(['get', 'post'], '/preview', [LegacyPagesController::class, 'preview'])->name('preview.legacy');
    Route::match(['get', 'post'], '/moresmilies', [LegacyPagesController::class, 'moresmilies'])->name('moresmilies.legacy');
    Route::match(['get', 'post'], '/smilies', [LegacyPagesController::class, 'smilies'])->name('smilies.legacy');
    Route::match(['get', 'post'], '/mailtest', [ManagementController::class, 'mailtest'])->name('mailtest.legacy');
    Route::match(['get', 'post'], '/mysql_stats', [ManagementController::class, 'mysqlStats'])->name('mysql_stats.legacy');
    Route::match(['get', 'post'], '/reset', [ManagementController::class, 'reset'])->name('reset.legacy');
    Route::match(['get', 'post'], '/self-enable', [ManagementController::class, 'selfEnable'])->name('self-enable.legacy');
    Route::match(['get', 'post'], '/unco', [ManagementController::class, 'unco'])->name('unco.legacy');
    Route::match(['get', 'post'], '/adduser', [ManagementController::class, 'adduser'])->name('adduser.legacy');
    Route::match(['get', 'post'], '/bitbucketlog', [LegacyPagesController::class, 'bitbucketlog'])->name('bitbucketlog.legacy');
    Route::match(['get', 'post'], '/delete', [LegacyPagesController::class, 'delete'])->name('delete.legacy');
    Route::match(['get', 'post'], '/downloadnotice', [LegacyPagesController::class, 'downloadnotice'])->name('downloadnotice.legacy');
    Route::match(['get', 'post'], '/increment-bulk', [ManagementController::class, 'incrementBulk'])->name('increment-bulk.legacy');
    Route::match(['get', 'post'], '/maxlogin', [ManagementController::class, 'maxlogin'])->name('maxlogin.legacy');
    Route::match(['get', 'post'], '/setlist_lookup', [ManagementController::class, 'setlistLookup'])->name('setlist_lookup.legacy');
    Route::match(['get', 'post'], '/take-increment-bulk', [ManagementController::class, 'takeIncrementBulk'])->name('take-increment-bulk.legacy');
    Route::match(['get', 'post'], '/testip', [ManagementController::class, 'testip'])->name('testip.legacy');
    Route::match(['get', 'post'], '/thanks', [LegacyPagesController::class, 'thanks'])->name('thanks.legacy');
});

Route::match(['get', 'post'], '/complains', [ManagementController::class, 'complains'])->name('complains.legacy');
Route::match(['get', 'post'], '/shoutbox', [ShoutboxController::class, 'shoutbox'])->name('shoutbox.legacy');

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