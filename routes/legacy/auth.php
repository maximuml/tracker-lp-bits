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
use App\Http\Controllers\StaffPageController;
use App\Http\Controllers\SystemBulkController;
use App\Http\Controllers\SystemMaintenanceController;
use App\Http\Controllers\TorrentAjaxController;
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
$redirect301 = fn (string $target) => fn (Request $request) => redirect()->to(
    $target.($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
);

Route::get('/upload', $redirect301('/web/upload'));
Route::get('/bitbucket-upload', $redirect301('/web/bitbucket-upload'));
Route::post('/bitbucket-upload', [BitbucketUploadController::class, 'store'])->middleware('throttle:upload');
Route::get('/offers', $redirect301('/web/offers'));
Route::post('/offers', [OfferController::class, 'legacyAction'])->middleware('reject.get.mutations');
Route::get('/torrents', $redirect301('/web/torrents'));
Route::get('/details/{id}', fn (Request $request) => redirect()->to(
    '/web/details/'.$request->route('id')
        .($request->getQueryString() !== null && $request->getQueryString() !== '' ? '?'.$request->getQueryString() : ''),
    301,
))->where('id', '[0-9]+');
Route::get('/mybonus', $redirect301('/web/mybonus'));
Route::post('/mybonus', [MyController::class, 'bonusExchange'])->middleware('reject.get.mutations');
Route::get('/my_bonus', $redirect301('/web/mybonus'));
Route::post('/my_bonus', [MyController::class, 'bonusExchange'])->middleware('reject.get.mutations');
Route::get('/myhr', $redirect301('/web/myhr'));
Route::get('/topten', $redirect301('/web/topten'));
Route::get('/log', $redirect301('/web/log'));
Route::post('/log', [LogController::class, 'legacyPost']);
Route::get('/index', $redirect301('/web/index'));
Route::post('/index', [IndexController::class, 'legacyPost']);
Route::get('/friends', $redirect301('/web/friends'));
Route::post('/friends', [FriendsController::class, 'friendsPost']);
Route::get('/messages', $redirect301('/web/messages'));
Route::post('/messages', [MessageController::class, 'messagesAction'])->middleware('reject.get.mutations');
Route::get('/getrss', $redirect301('/web/getrss'));
Route::post('/getrss', [RssController::class, 'getrssPost']);
Route::get('/sendmessage', $redirect301('/web/sendmessage'));
Route::get('/userhistory', $redirect301('/web/userhistory'));
Route::get('/invite', $redirect301('/web/invite'));
Route::post('/invite', [InviteController::class, 'inviteAction'])->middleware('reject.get.mutations');
Route::get('/news', $redirect301('/web/news'));
Route::post('/news', [NewsController::class, 'newsPost']);
Route::get('/makepoll', $redirect301('/web/makepoll'));
Route::post('/makepoll', [PollController::class, 'makepollPost']);
Route::get('/polloverview', $redirect301('/web/polloverview'));
Route::post('/polloverview', [PollController::class, 'polloverviewPost']);
Route::get('/attendance', $redirect301('/web/attendance'));
Route::post('/attendance', [AttendanceController::class, 'attendancePost']);
Route::post('/takemessage', [MessageController::class, 'takeMessage'])->middleware('reject.get.mutations')->name('takemessage.legacy');
Route::post('/deletemessage', [MessageController::class, 'deletemessage'])->middleware('reject.get.mutations')->name('deletemessage.legacy');
Route::get('/report', $redirect301('/web/report'));
Route::post('/report', [ModerationController::class, 'reportAction']);
Route::get('/reports', $redirect301('/web/reports'));

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
Route::get('/modtask', [StaffModerationController::class, 'modtask'])->name('modtask.legacy');
Route::post('/modtask', [StaffModerationController::class, 'modtaskPost']);
Route::get('/staff', [StaffPageController::class, 'staff'])->name('staff.legacy');

// Phase 5.4: staffbox migrated to Filament StaffMessageResource
Route::get('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy');
Route::post('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy.post');

// Phase 5.4: staffmess/takestaffmess (mass PM) kept as legacy for now — admin-only mass-mail form
Route::get('/staffmess', [StaffMessageController::class, 'staffmess'])->name('staffmess.legacy');
Route::post('/staffmess', [StaffMessageController::class, 'staffmessPost']);
Route::post('/takestaffmess', [StaffMessageController::class, 'takeStaffmess'])->middleware('reject.get.mutations')->name('takestaffmess.legacy');

// Phase 5.4: contactstaff stays legacy (user-facing form to contact staff)
Route::get('/contactstaff', [StaffMessageController::class, 'contactstaff'])->name('contactstaff.legacy');
Route::post('/contactstaff', [StaffMessageController::class, 'contactstaffPost']);
Route::post('/takecontact', [StaffMessageController::class, 'takecontact'])->middleware('reject.get.mutations')->name('takecontact.legacy');
Route::get('/modrules', [StaffModerationController::class, 'modrules'])->name('modrules.legacy');
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
Route::get('/user-ban-log', [AdminToolsController::class, 'userBanLog'])->name('user-ban-log.legacy');
Route::post('/user-ban-log', [AdminToolsController::class, 'userBanLogPost']);
Route::get('/torrent_info', [TorrentMaintenanceController::class, 'torrentInfo'])->name('torrent_info.legacy');
Route::get('/viewsnatches', [TorrentAjaxController::class, 'viewSnatches'])->name('viewsnatches.legacy');
Route::post('/takeflush', [TorrentMaintenanceController::class, 'takeFlush'])->middleware('reject.get.mutations')->name('takeflush.legacy');
Route::post('/takereseed', [TorrentMaintenanceController::class, 'takeReseed'])->middleware('reject.get.mutations')->name('takereseed.legacy');
Route::get('/clearcache', [AdminToolsController::class, 'clearCache'])->name('clearcache.legacy');
Route::post('/clearcache', [AdminToolsController::class, 'clearCachePost']);
Route::post('/fastdelete', [TorrentDeleteController::class, 'fastDelete'])->middleware('reject.get.mutations')->name('fastdelete.legacy');
Route::get('/donated', [InfoController::class, 'donated'])->name('donated.legacy');
Route::post('/donated', [InfoController::class, 'donatedPost']);
Route::get('/faqmanage', [FaqController::class, 'faqManage'])->name('faqmanage.legacy');
Route::post('/faqmanage', [FaqController::class, 'faqManagePost']);
Route::get('/faqactions', [FaqController::class, 'faqActions'])->name('faqactions.legacy');
Route::post('/faqactions', [FaqController::class, 'faqActionsPost']);
Route::get('/search', $redirect301('/web/search'));
Route::get('/usersearch', $redirect301('/web/usersearch'));
Route::get('/autocomplete_torrents', $redirect301('/web/autocomplete_torrents'));
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
Route::get('/attachment', $redirect301('/web/attachment'));
Route::post('/attachment', [UtilityController::class, 'attachmentStore'])->middleware('throttle:attachment');
Route::get('/getattachment', $redirect301('/web/getattachment'));
Route::get('/shoutbox_history', $redirect301('/web/shoutbox_history'));
Route::get('/notifications', $redirect301('/web/notifications'));
Route::post('/notifications', [NotificationController::class, 'markRead'])->middleware('throttle:notifications')->name('notifications.read.legacy');
Route::get('/latestcomments', $redirect301('/web/latestcomments'));
Route::get('/bonus-log', $redirect301('/web/bonus-log'));
Route::get('/task', $redirect301('/web/task'));
Route::get('/uploaders', $redirect301('/web/uploaders'));
Route::get('/settings', $redirect301('/web/settings'));
Route::post('/settings', [SettingsController::class, 'settingsAction'])->middleware('reject.get.mutations');
Route::get('/freeleech', $redirect301('/web/freeleech'));
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
Route::get('/users', [UserAdminController::class, 'users'])->name('users.legacy');
Route::get('/staffpanel', [StaffPageController::class, 'staffpanel'])->name('staffpanel.legacy');
Route::post('/docleanup', [SystemMaintenanceController::class, 'docleanup'])->middleware('reject.get.mutations')->name('docleanup.legacy');
Route::get('/location', [AdminToolsController::class, 'location'])->name('location.legacy');
Route::post('/location', [AdminToolsController::class, 'locationPost']);
Route::get('/preview', $redirect301('/web/preview'));
Route::post('/preview', [UtilityController::class, 'previewSubmit']);
Route::get('/moresmilies', $redirect301('/web/moresmilies'));
Route::get('/smilies', $redirect301('/web/smilies'));
Route::get('/mailtest', [SystemMaintenanceController::class, 'mailtest'])->name('mailtest.legacy');
Route::post('/mailtest', [SystemMaintenanceController::class, 'mailtestAction']);
Route::get('/mysql_stats', [SystemMaintenanceController::class, 'mysqlStats'])->name('mysql_stats.legacy');
Route::get('/reset', [UserAdminController::class, 'reset'])->name('reset.legacy');
Route::post('/reset', [UserAdminController::class, 'resetPost']);
Route::get('/self-enable', [UserAdminController::class, 'selfEnable'])->name('self-enable.legacy');
Route::post('/self-enable', [UserAdminController::class, 'selfEnablePost']);
Route::get('/unco', [UserAdminController::class, 'unco'])->name('unco.legacy');
Route::post('/unco', [UserAdminController::class, 'uncoPost']);
Route::get('/adduser', [UserAdminController::class, 'adduser'])->name('adduser.legacy');
Route::post('/adduser', [UserAdminController::class, 'adduserPost']);
Route::get('/bitbucketlog', [InfoController::class, 'bitbucketlog'])->name('bitbucketlog.legacy');
Route::post('/bitbucketlog', [InfoController::class, 'bitbucketlogPost']);
Route::post('/delete', [TorrentDeleteController::class, 'delete'])->middleware('reject.get.mutations')->name('delete.legacy');
Route::get('/downloadnotice', $redirect301('/web/downloadnotice'));
Route::post('/downloadnotice', [TorrentDownloadController::class, 'downloadnoticeAction'])->middleware('reject.get.mutations');
Route::post('/thanks', [TorrentBookmarkController::class, 'thanks'])->middleware('reject.get.mutations')->name('thanks.legacy');
Route::get('/increment-bulk', [SystemBulkController::class, 'incrementBulk'])->name('increment-bulk.legacy');
// Phase 5.6: maxlogin migrated to Filament LoginAttemptResource
Route::get('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy');
Route::post('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy.post');
Route::get('/setlist_lookup', [SystemBulkController::class, 'setlistLookup'])->name('setlist_lookup.legacy');
Route::post('/take-increment-bulk', [SystemBulkController::class, 'takeIncrementBulk'])->middleware('reject.get.mutations')->name('take-increment-bulk.legacy');
Route::get('/testip', [AdminToolsController::class, 'testip'])->name('testip.legacy');
Route::post('/testip', [AdminToolsController::class, 'testipPost']);
