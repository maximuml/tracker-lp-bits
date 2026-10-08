<?php

use App\Http\Controllers\BitbucketUploadController;
use App\Http\Controllers\FriendsController;
use App\Http\Controllers\LegacyRedirectController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OfferController;
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
        .(($qs = http_build_query($request->query->all())) !== '' ? '?'.$qs : ''),
    301,
))->where('id', '[0-9]+');
$get301('/mybonus', '/web/mybonus');
Route::post('/mybonus', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
$get301('/my_bonus', '/web/mybonus');
Route::post('/my_bonus', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
$get301('/myhr', '/web/myhr');
$get301('/topten', '/web/topten');
$get301('/log', '/web/log');
Route::post('/log', [LogController::class, 'legacyPost']);
$get301('/index', '/web/index');
Route::post('/index', [LegacyRedirectController::class, 'post']);
$get301('/friends', '/web/friends');
Route::post('/friends', [FriendsController::class, 'friendsPost']);
$get301('/messages', '/web/messages');
Route::post('/messages', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
$get301('/getrss', '/web/getrss');
Route::post('/getrss', [LegacyRedirectController::class, 'post']);
$get301('/sendmessage', '/web/sendmessage');
$get301('/userhistory', '/web/userhistory');
$get301('/invite', '/web/invite');
Route::post('/invite', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
$get301('/news', '/web/news');
Route::post('/news', [NewsController::class, 'newsPost']);
$get301('/makepoll', '/web/makepoll');
Route::post('/makepoll', [LegacyRedirectController::class, 'post']);
$get301('/polloverview', '/web/polloverview');
Route::post('/polloverview', [LegacyRedirectController::class, 'post']);
$get301('/attendance', '/web/attendance');
Route::post('/attendance', [LegacyRedirectController::class, 'post']);
Route::post('/takemessage', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takemessage.legacy');
Route::post('/deletemessage', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('deletemessage.legacy');
$get301('/report', '/web/report');
Route::post('/report', [LegacyRedirectController::class, 'post']);
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
Route::post('/modtask', [LegacyRedirectController::class, 'post']);
$get301('/staff', '/web/staff');

// Phase 5.4: staffbox migrated to Filament StaffMessageResource
Route::get('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy');
Route::post('/staffbox', fn () => redirect('/nexusphp/security/staff-messages'))->name('staffbox.legacy.post');

// Phase 5.4: staffmess/takestaffmess (mass PM) kept as legacy for now — admin-only mass-mail form
$get301('/staffmess', '/web/staffmess');
Route::post('/staffmess', [LegacyRedirectController::class, 'post']);
Route::post('/takestaffmess', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takestaffmess.legacy');
// take* URIs keep replaying the body at the renamed /web/* endpoints.

// Phase 5.4: contactstaff stays legacy (user-facing form to contact staff)
$get301('/contactstaff', '/web/contactstaff');
Route::post('/contactstaff', [LegacyRedirectController::class, 'post']);
Route::post('/takecontact', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takecontact.legacy');
$get301('/modrules', '/web/modrules');
Route::post('/modrules', [LegacyRedirectController::class, 'post']);

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
Route::post('/user-ban-log', [LegacyRedirectController::class, 'post']);
$get301('/torrent_info', '/web/torrent_info');
$get301('/viewsnatches', '/web/viewsnatches');
Route::post('/takeflush', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takeflush.legacy');
Route::post('/takereseed', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takereseed.legacy');
$get301('/clearcache', '/web/clearcache');
Route::post('/clearcache', [LegacyRedirectController::class, 'post']);
Route::post('/fastdelete', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('fastdelete.legacy');
$get301('/donated', '/web/donated');
Route::post('/donated', [LegacyRedirectController::class, 'post']);
$get301('/faqmanage', '/web/faqmanage');
Route::post('/faqmanage', [LegacyRedirectController::class, 'post']);
$get301('/faqactions', '/web/faqactions');
Route::post('/faqactions', [LegacyRedirectController::class, 'post']);
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
Route::post('/attachment', [LegacyRedirectController::class, 'post'])->middleware('throttle:attachment');
$get301('/getattachment', '/web/getattachment');
$get301('/shoutbox_history', '/web/shoutbox_history');
$get301('/notifications', '/web/notifications');
Route::post('/notifications', [LegacyRedirectController::class, 'post'])->middleware('throttle:notifications')->name('notifications.read.legacy');
$get301('/latestcomments', '/web/latestcomments');
$get301('/bonus-log', '/web/bonus-log');
$get301('/task', '/web/task');
$get301('/uploaders', '/web/uploaders');
$get301('/settings', '/web/settings');
Route::post('/settings', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
$get301('/freeleech', '/web/freeleech');
Route::post('/freeleech', [LegacyRedirectController::class, 'post']);
Route::post('/magic', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('magic.legacy');
// Phase 5.6: delacctadmin/deletedisabled/massmail migrated to Filament SystemActions page
Route::get('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy');
Route::post('/delacctadmin', fn () => redirect('/nexusphp/system-actions'))->name('delacctadmin.legacy.post');
Route::get('/deletedisabled', fn () => redirect('/nexusphp/system-actions'))->name('deletedisabled.legacy');
Route::get('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy');
Route::post('/massmail', fn () => redirect('/nexusphp/system-actions'))->name('massmail.legacy.post');
Route::post('/takeamountupload', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takeamountupload.legacy');
Route::post('/takeinvite', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takeinvite.legacy');
Route::post('/takeupdate', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('takeupdate.legacy');
$get301('/users', '/web/users');
$get301('/staffpanel', '/web/staffpanel');
Route::post('/docleanup', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('docleanup.legacy');
$get301('/location', '/web/location');
Route::post('/location', [LegacyRedirectController::class, 'post']);
$get301('/preview', '/web/preview');
Route::post('/preview', [LegacyRedirectController::class, 'post']);
$get301('/moresmilies', '/web/moresmilies');
$get301('/smilies', '/web/smilies');
$get301('/mailtest', '/web/mailtest');
Route::post('/mailtest', [LegacyRedirectController::class, 'post']);
$get301('/mysql_stats', '/web/mysql_stats');
$get301('/reset', '/web/reset');
Route::post('/reset', [LegacyRedirectController::class, 'post']);
$get301('/self-enable', '/web/self-enable');
Route::post('/self-enable', [LegacyRedirectController::class, 'post']);
$get301('/unco', '/web/unco');
Route::post('/unco', [LegacyRedirectController::class, 'post']);
$get301('/adduser', '/web/adduser');
Route::post('/adduser', [LegacyRedirectController::class, 'post']);
$get301('/bitbucketlog', '/web/bitbucketlog');
Route::post('/bitbucketlog', [LegacyRedirectController::class, 'post']);
Route::post('/delete', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('delete.legacy');
$get301('/downloadnotice', '/web/downloadnotice');
Route::post('/downloadnotice', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations');
Route::post('/thanks', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('thanks.legacy');
$get301('/increment-bulk', '/web/increment-bulk');
// Phase 5.6: maxlogin migrated to Filament LoginAttemptResource
Route::get('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy');
Route::post('/maxlogin', fn () => redirect('/nexusphp/security/login-attempts'))->name('maxlogin.legacy.post');
$get301('/setlist_lookup', '/web/setlist_lookup');
Route::post('/take-increment-bulk', [LegacyRedirectController::class, 'post'])->middleware('reject.get.mutations')->name('take-increment-bulk.legacy');
$get301('/testip', '/web/testip');
Route::post('/testip', [LegacyRedirectController::class, 'post']);
