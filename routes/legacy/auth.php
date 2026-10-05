<?php

use App\Http\Controllers\AdminToolsController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BitbucketUploadController;
use App\Http\Controllers\BonusHistoryController;
use App\Http\Controllers\BonusShopController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StaffMessageController;
use App\Http\Controllers\StaffModerationController;
use App\Http\Controllers\SystemBulkController;
use App\Http\Controllers\SystemMaintenanceController;
use App\Http\Controllers\TorrentBookmarkController;
use App\Http\Controllers\TorrentDeleteController;
use App\Http\Controllers\TorrentDownloadController;
use App\Http\Controllers\TorrentMaintenanceController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\WebCommentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Renamed endpoints — the canonical GET URIs now live under /web/* in
// routes/web.php; 301 forwards the query string unchanged.
$get301 = static fn (string $from, string $to) => Route::get($from, [LegacyRedirectController::class, 'get'])->defaults('redirect_to', $to);

$get301('/upload', '/web/upload');
$get301('/bitbucket-upload', '/web/bitbucket-upload');
Route::post('/bitbucket-upload', [BitbucketUploadController::class, 'store'])->middleware('throttle:upload');
$get301('/offers', '/web/offers');
Route::post('/offers', [OfferController::class, 'legacyAction'])->middleware('reject.get.mutations');
$get301('/torrents', '/web/torrents');
Route::get('/details/{id}', fn (Request $request) => redirect()->to(
    '/web/details/'.$request->route('id')
        .($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
))->where('id', '[0-9]+');
$get301('/mybonus', '/web/mybonus');
Route::post('/mybonus', [MyController::class, 'bonusExchange'])->middleware('reject.get.mutations');
$get301('/my_bonus', '/web/mybonus');
Route::post('/my_bonus', [MyController::class, 'bonusExchange'])->middleware('reject.get.mutations');
$get301('/myhr', '/web/myhr');
$get301('/topten', '/web/topten');
$get301('/log', '/web/log');
Route::post('/log', [LogController::class, 'legacyPost']);
$get301('/index', '/web/index');
Route::post('/index', [IndexController::class, 'legacyPost']);
$get301('/friends', '/web/friends');
Route::post('/friends', [FriendsController::class, 'friendsPost']);
$get301('/messages', '/web/messages');
Route::post('/messages', [MessageController::class, 'messagesAction'])->middleware('reject.get.mutations');
$get301('/getrss', '/web/getrss');
Route::post('/getrss', [RssController::class, 'getrssPost']);
$get301('/sendmessage', '/web/sendmessage');
$get301('/userhistory', '/web/userhistory');
$get301('/invite', '/web/invite');
Route::post('/invite', [InviteController::class, 'inviteAction'])->middleware('reject.get.mutations');
$get301('/news', '/web/news');
Route::post('/news', [NewsController::class, 'newsPost']);
$get301('/makepoll', '/web/makepoll');
Route::post('/makepoll', [PollController::class, 'makepollPost']);
$get301('/polloverview', '/web/polloverview');
Route::post('/polloverview', [PollController::class, 'polloverviewPost']);
$get301('/attendance', '/web/attendance');
Route::post('/attendance', [AttendanceController::class, 'attendancePost']);
Route::post('/takemessage', [MessageController::class, 'takeMessage'])->middleware('reject.get.mutations')->name('takemessage.legacy');
Route::post('/deletemessage', [MessageController::class, 'deletemessage'])->middleware('reject.get.mutations')->name('deletemessage.legacy');
$get301('/report', '/web/report');
Route::post('/report', [ModerationController::class, 'reportAction']);
$get301('/reports', '/web/reports');

// Phase 5.3: bans/cheaters/ipcheck migrated to Filament SecurityResource group
Route::get('/bans', fn () => redirect('/nexusphp/security/bans'))->name('bans.legacy');
Route::post('/bans', fn () => redirect('/nexusphp/security/bans'))->name('bans.legacy.post');
Route::get('/cheaterbox', fn () => redirect('/nexusphp/security/cheaters'))->name('cheaterbox.legacy');
Route::get('/cheaters', fn () => redirect('/nexusphp/security/cheaters'))->name('cheaters.legacy');
Route::get('/ipcheck', fn () => redirect('/nexusphp/user/users'))->name('ipcheck.legacy');

// Phase 5.3: iphistory/ipsearch redirect to Filament user view (IP shown on profile for moderators)
Route::get('/iphistory', function (Request $request) {
    $id = (int) $request->query('id', 0);

    return $id > 0 ? redirect("/nexusphp/user/users/{$id}") : redirect('/nexusphp/user/users');
})->name('iphistory.legacy');
Route::get('/ipsearch', fn () => redirect('/nexusphp/user/users'))->name('ipsearch.legacy');
$get301('/modtask', '/web/modtask');
Route::post('/modtask', [StaffModerationController::class, 'modtaskPost']);
$get301('/staff', '/web/staff');

// Phase 5.4: staffbox migrated to Filament StaffMessageResource
Route::get('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy');
Route::post('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy.post');

// Phase 5.4: staffmess/takestaffmess (mass PM) kept as legacy for now — admin-only mass-mail form
$get301('/staffmess', '/web/staffmess');
Route::post('/staffmess', [StaffMessageController::class, 'staffmessPost']);
Route::post('/takestaffmess', [StaffMessageController::class, 'takeStaffmess'])->middleware('reject.get.mutations')->name('takestaffmess.legacy');

// Phase 5.4: contactstaff stays legacy (user-facing form to contact staff)
$get301('/contactstaff', '/web/contactstaff');
Route::post('/contactstaff', [StaffMessageController::class, 'contactstaffPost']);
Route::post('/takecontact', [StaffMessageController::class, 'takecontact'])->middleware('reject.get.mutations')->name('takecontact.legacy');
$get301('/modrules', '/web/modrules');
Route::post('/modrules', [StaffModerationController::class, 'modrulesPost']);

// Phase 5.5: stats/allagents migrated to Filament dashboard widgets
Route::get('/stats', fn () => redirect('/nexusphp'))->name('stats.legacy');
Route::get('/allagents', fn () => redirect('/nexusphp'))->name('allagents.legacy');

// Phase 5.2: donorlist/warned/nowarn migrated to Filament UserResource filters + bulk actions
Route::get('/donorlist', fn () => redirect('/nexusphp/user/users?tableFilters[is_donating][value]=yes'))->name('donorlist.legacy');
Route::get('/warned', fn () => redirect('/nexusphp/user/users?tableFilters[warned][value]=yes'))->name('warned.legacy');
Route::post('/nowarn', fn () => redirect('/nexusphp/user/users?tableFilters[warned][value]=yes'))->name('nowarn.legacy');

// Phase 5.1: checkuser/takeconfirm migrated to Filament UserResource
Route::get('/checkuser', function (Request $request) {
    $id = (int) $request->query('id', 0);

    return $id > 0
        ? redirect("/nexusphp/user/users/{$id}")
        : redirect('/nexusphp/user/users');
})->name('checkuser.legacy');
Route::post('/takeconfirm', function (Request $request) {
    $id = (int) $request->input('id', $request->query('id', 0));

    return $id > 0
        ? redirect("/nexusphp/user/users/{$id}")
        : redirect('/nexusphp/user/users');
})->name('takeconfirm.legacy');
$get301('/user-ban-log', '/web/user-ban-log');
Route::post('/user-ban-log', [AdminToolsController::class, 'userBanLogPost']);
$get301('/torrent_info', '/web/torrent_info');
$get301('/viewsnatches', '/web/viewsnatches');
Route::post('/takeflush', [TorrentMaintenanceController::class, 'takeFlush'])->middleware('reject.get.mutations')->name('takeflush.legacy');
Route::post('/takereseed', [TorrentMaintenanceController::class, 'takeReseed'])->middleware('reject.get.mutations')->name('takereseed.legacy');
$get301('/clearcache', '/web/clearcache');
Route::post('/clearcache', [AdminToolsController::class, 'clearCachePost']);
Route::post('/fastdelete', [TorrentDeleteController::class, 'fastDelete'])->middleware('reject.get.mutations')->name('fastdelete.legacy');
$get301('/donated', '/web/donated');
Route::post('/donated', [InfoController::class, 'donatedPost']);
$get301('/faqmanage', '/web/faqmanage');
Route::post('/faqmanage', [FaqController::class, 'faqManagePost']);
$get301('/faqactions', '/web/faqactions');
Route::post('/faqactions', [FaqController::class, 'faqActionsPost']);
$get301('/search', '/web/search');
$get301('/usersearch', '/web/usersearch');
$get301('/autocomplete_torrents', '/web/autocomplete_torrents');
Route::get('/comment/add', [WebCommentController::class, 'create'])->middleware('throttle:comment');
Route::post('/comment', [WebCommentController::class, 'store'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/edit', [WebCommentController::class, 'edit'])->middleware('throttle:comment');
Route::post('/comment/{commentId}/edit', [WebCommentController::class, 'update'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/delete', [WebCommentController::class, 'deleteConfirm'])->middleware('throttle:comment');
Route::post('/comment/{commentId}/delete', [WebCommentController::class, 'destroy'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/original', [WebCommentController::class, 'original'])->middleware('throttle:comment');
// Phase 5.7: catmanage/forummanage/moforums/fields/formats migrated to Filament Section resources
Route::get('/catmanage', fn () => redirect('/nexusphp/section/categories'))->name('catmanage.legacy');
Route::get('/forummanage', fn () => redirect('/nexusphp/section/forums'))->name('forummanage.legacy');
Route::get('/moforums', fn () => redirect('/nexusphp/section/over-forums'))->name('moforums.legacy');
Route::get('/fields', fn () => redirect('/nexusphp/torrent-custom-fields'))->name('fields.legacy');
Route::get('/formats', fn () => redirect('/nexusphp/section/codecs'))->name('formats.legacy');
Route::get('/videoformats', fn () => redirect('/nexusphp/section/standards'))->name('videoformats.legacy');
$get301('/attachment', '/web/attachment');
Route::post('/attachment', [UtilityController::class, 'attachmentStore'])->middleware('throttle:attachment');
$get301('/getattachment', '/web/getattachment');
$get301('/shoutbox_history', '/web/shoutbox_history');
$get301('/notifications', '/web/notifications');
Route::post('/notifications', [NotificationController::class, 'markRead'])->middleware('throttle:notifications')->name('notifications.read.legacy');
$get301('/latestcomments', '/web/latestcomments');
$get301('/bonus-log', '/web/bonus-log');
$get301('/task', '/web/task');
$get301('/uploaders', '/web/uploaders');
$get301('/settings', '/web/settings');
Route::post('/settings', [SettingsController::class, 'settingsAction'])->middleware('reject.get.mutations');
$get301('/freeleech', '/web/freeleech');
Route::post('/freeleech', [BonusShopController::class, 'freeleechPost']);
Route::post('/magic', [BonusHistoryController::class, 'magic'])->middleware('reject.get.mutations')->name('magic.legacy');
// Phase 5.6: delacctadmin/deletedisabled/massmail migrated to Filament SystemActions page
Route::get('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy');
Route::post('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy.post');
Route::get('/deletedisabled', fn () => redirect('/nexusphp/system-actions'))->name('deletedisabled.legacy');
Route::get('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy');
Route::post('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy.post');
Route::post('/takeamountupload', [SystemBulkController::class, 'takeamountupload'])->middleware('reject.get.mutations')->name('takeamountupload.legacy');
Route::post('/takeinvite', [SystemBulkController::class, 'takeinvite'])->middleware('reject.get.mutations')->name('takeinvite.legacy');
Route::post('/takeupdate', [SystemBulkController::class, 'takeupdate'])->middleware('reject.get.mutations')->name('takeupdate.legacy');
$get301('/users', '/web/users');
$get301('/staffpanel', '/web/staffpanel');
Route::post('/docleanup', [SystemMaintenanceController::class, 'docleanup'])->middleware('reject.get.mutations')->name('docleanup.legacy');
$get301('/location', '/web/location');
Route::post('/location', [AdminToolsController::class, 'locationPost']);
$get301('/preview', '/web/preview');
Route::post('/preview', [UtilityController::class, 'previewSubmit']);
$get301('/moresmilies', '/web/moresmilies');
$get301('/smilies', '/web/smilies');
$get301('/mailtest', '/web/mailtest');
Route::post('/mailtest', [SystemMaintenanceController::class, 'mailtestAction']);
$get301('/mysql_stats', '/web/mysql_stats');
$get301('/reset', '/web/reset');
Route::post('/reset', [UserAdminController::class, 'resetPost']);
$get301('/self-enable', '/web/self-enable');
Route::post('/self-enable', [UserAdminController::class, 'selfEnablePost']);
$get301('/unco', '/web/unco');
Route::post('/unco', [UserAdminController::class, 'uncoPost']);
$get301('/adduser', '/web/adduser');
Route::post('/adduser', [UserAdminController::class, 'adduserPost']);
$get301('/bitbucketlog', '/web/bitbucketlog');
Route::post('/bitbucketlog', [InfoController::class, 'bitbucketlogPost']);
Route::post('/delete', [TorrentDeleteController::class, 'delete'])->middleware('reject.get.mutations')->name('delete.legacy');
$get301('/downloadnotice', '/web/downloadnotice');
Route::post('/downloadnotice', [TorrentDownloadController::class, 'downloadnoticeAction'])->middleware('reject.get.mutations');
Route::post('/thanks', [TorrentBookmarkController::class, 'thanks'])->middleware('reject.get.mutations')->name('thanks.legacy');
$get301('/increment-bulk', '/web/increment-bulk');
// Phase 5.6: maxlogin migrated to Filament LoginAttemptResource
Route::get('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy');
Route::post('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy.post');
$get301('/setlist_lookup', '/web/setlist_lookup');
Route::post('/take-increment-bulk', [SystemBulkController::class, 'takeIncrementBulk'])->middleware('reject.get.mutations')->name('take-increment-bulk.legacy');
$get301('/testip', '/web/testip');
Route::post('/testip', [AdminToolsController::class, 'testipPost']);
