<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\BitbucketUploadController;
use App\Http\Controllers\BonusController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\IndexController;
use App\Http\Controllers\InfoController;
use App\Http\Controllers\InviteController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\MyController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\RssController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\ToptenController;
use App\Http\Controllers\TorrentActionController;
use App\Http\Controllers\TorrentDetailsController;
use App\Http\Controllers\TorrentListingController;
use App\Http\Controllers\TorrentUploadController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\WebCommentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/upload', [TorrentUploadController::class, 'create'])->middleware('throttle:upload')->name('torrents.upload');
Route::get('/bitbucket-upload', [BitbucketUploadController::class, 'create'])->middleware('throttle:upload')->name('bitbucket.upload');
Route::post('/bitbucket-upload', [BitbucketUploadController::class, 'store'])->middleware('throttle:upload');
Route::match(['get', 'post'], '/offers', [OfferController::class, 'legacy'])->name('offers.legacy');
Route::get('/torrents', [TorrentListingController::class, 'index'])->middleware('throttle:torrents')->name('torrents.index');
Route::get('/details/{id}', [TorrentDetailsController::class, 'show'])->where('id', '[0-9]+')->name('torrent.details');
Route::match(['get', 'post'], '/mybonus', [MyController::class, 'bonus'])->name('my.bonus');
Route::match(['get', 'post'], '/my_bonus', [MyController::class, 'bonus'])->name('my.bonus.underscore');
Route::get('/myhr', [MyController::class, 'hr'])->name('my.hr');
Route::get('/topten', [ToptenController::class, 'legacy'])->name('topten.legacy');
Route::get('/log', [LogController::class, 'legacy'])->name('log.legacy');
Route::match(['get', 'post'], '/index', [IndexController::class, 'legacy'])->name('index.legacy');
Route::match(['get', 'post'], '/friends', [FriendsController::class, 'friends'])->name('friends.legacy');
Route::match(['get', 'post'], '/messages', [MessageController::class, 'messages'])->name('messages.legacy');
Route::match(['get', 'post'], '/getrss', [RssController::class, 'getrss'])->name('getrss.legacy');
Route::match(['get', 'post'], '/sendmessage', [MessageController::class, 'sendmessage'])->name('sendmessage.legacy');
Route::match(['get', 'post'], '/userhistory', [InfoController::class, 'userhistory'])->name('userhistory.legacy');
Route::match(['get', 'post'], '/invite', [InviteController::class, 'invite'])->name('invite.legacy');
Route::match(['get', 'post'], '/news', [NewsController::class, 'news'])->name('news.legacy');
Route::match(['get', 'post'], '/makepoll', [PollController::class, 'makepoll'])->name('makepoll.legacy');
Route::match(['get', 'post'], '/polloverview', [PollController::class, 'polloverview'])->name('polloverview.legacy');
Route::match(['get', 'post'], '/attendance', [AttendanceController::class, 'attendance'])->name('attendance.legacy');
Route::match(['get', 'post'], '/takemessage', [MessageController::class, 'takeMessage'])->name('takemessage.legacy');
Route::match(['get', 'post'], '/deletemessage', [MessageController::class, 'deletemessage'])->name('deletemessage.legacy');
Route::match(['get', 'post'], '/report', [ModerationController::class, 'report'])->name('report.legacy');
Route::match(['get', 'post'], '/reports', [ModerationController::class, 'reports'])->name('reports.legacy');

// Phase 5.3: bans/cheaters/ipcheck migrated to Filament SecurityResource group
Route::get('/bans', fn () => redirect('/nexusphp/bans'))->name('bans.legacy');
Route::post('/bans', fn () => redirect('/nexusphp/bans'))->name('bans.legacy.post');
Route::get('/cheaterbox', fn () => redirect('/nexusphp/cheaters'))->name('cheaterbox.legacy');
Route::get('/cheaters', fn () => redirect('/nexusphp/cheaters'))->name('cheaters.legacy');
Route::get('/ipcheck', fn () => redirect('/nexusphp/users'))->name('ipcheck.legacy');

// Phase 5.3: iphistory/ipsearch redirect to Filament user view (IP shown on profile for moderators)
Route::get('/iphistory', function (Request $request) {
    $id = (int) $request->query('id', 0);

    return $id > 0 ? redirect("/nexusphp/users/{$id}") : redirect('/nexusphp/users');
})->name('iphistory.legacy');
Route::get('/ipsearch', fn () => redirect('/nexusphp/users'))->name('ipsearch.legacy');
Route::match(['get', 'post'], '/modtask', [StaffController::class, 'modtask'])->name('modtask.legacy');
Route::match(['get', 'post'], '/staff', [StaffController::class, 'staff'])->name('staff.legacy');

// Phase 5.4: staffbox migrated to Filament StaffMessageResource
Route::get('/staffbox', fn () => redirect('/nexusphp/staff-messages'))->name('staffbox.legacy');
Route::post('/staffbox', fn () => redirect('/nexusphp/staff-messages'))->name('staffbox.legacy.post');

// Phase 5.4: staffmess/takestaffmess (mass PM) kept as legacy for now — admin-only mass-mail form
Route::match(['get', 'post'], '/staffmess', [StaffController::class, 'staffmess'])->name('staffmess.legacy');
Route::match(['get', 'post'], '/takestaffmess', [StaffController::class, 'takeStaffmess'])->name('takestaffmess.legacy');

// Phase 5.4: contactstaff stays legacy (user-facing form to contact staff)
Route::match(['get', 'post'], '/contactstaff', [StaffController::class, 'contactstaff'])->name('contactstaff.legacy');
Route::match(['get', 'post'], '/takecontact', [StaffController::class, 'takecontact'])->name('takecontact.legacy');
Route::match(['get', 'post'], '/modrules', [StaffController::class, 'modrules'])->name('modrules.legacy');

// Phase 5.5: stats/allagents migrated to Filament dashboard widgets
Route::get('/stats', fn () => redirect('/nexusphp'))->name('stats.legacy');
Route::get('/allagents', fn () => redirect('/nexusphp'))->name('allagents.legacy');

// Phase 5.2: donorlist/warned/nowarn migrated to Filament UserResource filters + bulk actions
Route::get('/donorlist', fn () => redirect('/nexusphp/users?tableFilters[is_donating][value]=yes'))->name('donorlist.legacy');
Route::get('/warned', fn () => redirect('/nexusphp/users?tableFilters[warned][value]=yes'))->name('warned.legacy');
Route::post('/nowarn', fn () => redirect('/nexusphp/users?tableFilters[warned][value]=yes'))->name('nowarn.legacy');

// Phase 5.1: checkuser/takeconfirm migrated to Filament UserResource
Route::get('/checkuser', function (Request $request) {
    $id = (int) $request->query('id', 0);

    return $id > 0
        ? redirect("/nexusphp/users/{$id}")
        : redirect('/nexusphp/users');
})->name('checkuser.legacy');
Route::post('/takeconfirm', function (Request $request) {
    $id = (int) $request->input('id', $request->query('id', 0));

    return $id > 0
        ? redirect("/nexusphp/users/{$id}")
        : redirect('/nexusphp/users');
})->name('takeconfirm.legacy');
Route::match(['get', 'post'], '/user-ban-log', [AdminController::class, 'userBanLog'])->name('user-ban-log.legacy');
Route::match(['get', 'post'], '/torrent_info', [TorrentActionController::class, 'torrentInfo'])->name('torrent_info.legacy');
Route::match(['get', 'post'], '/viewsnatches', [TorrentActionController::class, 'viewSnatches'])->name('viewsnatches.legacy');
Route::match(['get', 'post'], '/takeflush', [TorrentActionController::class, 'takeFlush'])->name('takeflush.legacy');
Route::match(['get', 'post'], '/takereseed', [TorrentActionController::class, 'takeReseed'])->name('takereseed.legacy');
Route::match(['get', 'post'], '/clearcache', [AdminController::class, 'clearCache'])->name('clearcache.legacy');
Route::match(['get', 'post'], '/fastdelete', [TorrentActionController::class, 'fastDelete'])->name('fastdelete.legacy');
Route::match(['get', 'post'], '/donated', [InfoController::class, 'donated'])->name('donated.legacy');
Route::match(['get', 'post'], '/faqmanage', [FaqController::class, 'faqManage'])->name('faqmanage.legacy');
Route::match(['get', 'post'], '/faqactions', [FaqController::class, 'faqActions'])->name('faqactions.legacy');
Route::match(['get', 'post'], '/search', [UtilityController::class, 'search'])->name('search.legacy');
Route::match(['get', 'post'], '/usersearch', [UtilityController::class, 'usersearch'])->name('usersearch.legacy');
Route::match(['get', 'post'], '/autocomplete_torrents', [TorrentActionController::class, 'autocompleteTorrents'])->name('autocomplete_torrents.legacy');
Route::get('/comment/add', [WebCommentController::class, 'create'])->middleware('throttle:comment');
Route::post('/comment', [WebCommentController::class, 'store'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/edit', [WebCommentController::class, 'edit'])->middleware('throttle:comment');
Route::post('/comment/{commentId}/edit', [WebCommentController::class, 'update'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/delete', [WebCommentController::class, 'destroy'])->middleware('throttle:comment');
Route::get('/comment/{commentId}/original', [WebCommentController::class, 'original'])->middleware('throttle:comment');
// Phase 5.7: catmanage/forummanage/moforums/fields/formats migrated to Filament Section resources
Route::get('/catmanage', fn () => redirect('/nexusphp/section/categories'))->name('catmanage.legacy');
Route::get('/forummanage', fn () => redirect('/nexusphp/section/forums'))->name('forummanage.legacy');
Route::get('/moforums', fn () => redirect('/nexusphp/section/over-forums'))->name('moforums.legacy');
Route::get('/fields', fn () => redirect('/nexusphp/torrent-custom-fields'))->name('fields.legacy');
Route::get('/formats', fn () => redirect('/nexusphp/section/codecs'))->name('formats.legacy');
Route::get('/videoformats', fn () => redirect('/nexusphp/section/standards'))->name('videoformats.legacy');
Route::match(['get', 'post'], '/attachment', [UtilityController::class, 'attachment'])->middleware('throttle:attachment')->name('attachment.legacy');
Route::match(['get', 'post'], '/getattachment', [UtilityController::class, 'getattachment'])->middleware('throttle:attachment')->name('getattachment.legacy');
Route::match(['get', 'post'], '/shoutbox_history', [ShoutboxController::class, 'shoutboxHistory'])->name('shoutbox_history.legacy');
Route::match(['get', 'post'], '/latestcomments', [ForumController::class, 'latestcomments'])->name('latestcomments.legacy');
Route::match(['get', 'post'], '/bonus-log', [BonusController::class, 'bonusLog'])->name('bonus-log.legacy');
Route::match(['get', 'post'], '/medal', [BonusController::class, 'medal'])->name('medal.legacy');
Route::match(['get', 'post'], '/task', [BonusController::class, 'task'])->name('task.legacy');
Route::match(['get', 'post'], '/uploaders', [BonusController::class, 'uploaders'])->name('uploaders.legacy');
Route::match(['get', 'post'], '/settings', [AdminController::class, 'settings'])->name('settings.legacy');
Route::match(['get', 'post'], '/freeleech', [BonusController::class, 'freeleech'])->name('freeleech.legacy');
Route::match(['get', 'post'], '/magic', [BonusController::class, 'magic'])->name('magic.legacy');
// Phase 5.6: delacctadmin/deletedisabled/massmail migrated to Filament SystemActions page
Route::get('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy');
Route::post('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy.post');
Route::get('/deletedisabled', fn () => redirect('/nexusphp/system-actions'))->name('deletedisabled.legacy');
Route::get('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy');
Route::post('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy.post');
Route::match(['get', 'post'], '/takeamountupload', [SystemController::class, 'takeamountupload'])->name('takeamountupload.legacy');
Route::match(['get', 'post'], '/takeinvite', [SystemController::class, 'takeinvite'])->name('takeinvite.legacy');
Route::match(['get', 'post'], '/takeupdate', [SystemController::class, 'takeupdate'])->name('takeupdate.legacy');
Route::match(['get', 'post'], '/users', [AdminController::class, 'users'])->name('users.legacy');
Route::match(['get', 'post'], '/staffpanel', [StaffController::class, 'staffpanel'])->name('staffpanel.legacy');
Route::match(['get', 'post'], '/docleanup', [SystemController::class, 'docleanup'])->name('docleanup.legacy');
Route::match(['get', 'post'], '/location', [AdminController::class, 'location'])->name('location.legacy');
Route::match(['get', 'post'], '/preview', [UtilityController::class, 'preview'])->name('preview.legacy');
Route::match(['get', 'post'], '/moresmilies', [UtilityController::class, 'moresmilies'])->name('moresmilies.legacy');
Route::match(['get', 'post'], '/smilies', [UtilityController::class, 'smilies'])->name('smilies.legacy');
Route::match(['get', 'post'], '/mailtest', [SystemController::class, 'mailtest'])->name('mailtest.legacy');
Route::match(['get', 'post'], '/mysql_stats', [SystemController::class, 'mysqlStats'])->name('mysql_stats.legacy');
Route::match(['get', 'post'], '/reset', [AdminController::class, 'reset'])->name('reset.legacy');
Route::match(['get', 'post'], '/self-enable', [AdminController::class, 'selfEnable'])->name('self-enable.legacy');
Route::match(['get', 'post'], '/unco', [AdminController::class, 'unco'])->name('unco.legacy');
Route::match(['get', 'post'], '/adduser', [AdminController::class, 'adduser'])->name('adduser.legacy');
Route::match(['get', 'post'], '/bitbucketlog', [InfoController::class, 'bitbucketlog'])->name('bitbucketlog.legacy');
Route::match(['get', 'post'], '/delete', [TorrentActionController::class, 'delete'])->name('delete.legacy');
Route::match(['get', 'post'], '/downloadnotice', [TorrentActionController::class, 'downloadnotice'])->name('downloadnotice.legacy');
Route::match(['get', 'post'], '/thanks', [TorrentActionController::class, 'thanks'])->name('thanks.legacy');
Route::match(['get', 'post'], '/increment-bulk', [SystemController::class, 'incrementBulk'])->name('increment-bulk.legacy');
// Phase 5.6: maxlogin migrated to Filament LoginAttemptResource
Route::get('/maxlogin', fn () => redirect('/nexusphp/login-attempts'))->name('maxlogin.legacy');
Route::post('/maxlogin', fn () => redirect('/nexusphp/login-attempts'))->name('maxlogin.legacy.post');
Route::match(['get', 'post'], '/setlist_lookup', [SystemController::class, 'setlistLookup'])->name('setlist_lookup.legacy');
Route::match(['get', 'post'], '/take-increment-bulk', [SystemController::class, 'takeIncrementBulk'])->name('take-increment-bulk.legacy');
Route::match(['get', 'post'], '/testip', [AdminController::class, 'testip'])->name('testip.legacy');
