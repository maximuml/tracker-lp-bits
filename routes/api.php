<?php

use App\Enums\Permission\RoutePermissionEnum;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth:sanctum']], function () {

    Route::group(['middleware' => ['checkUserStatus']], function () {

        Route::post('logout', [\App\Http\Controllers\AuthenticateController::class, 'logout'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AUTH_LOGOUT));

        Route::get('user-me', [\App\Http\Controllers\UserController::class, 'me'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_ME));
        Route::get('user-publish-torrent', [\App\Http\Controllers\UserController::class, 'publishTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-seeding-torrent', [\App\Http\Controllers\UserController::class, 'seedingTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-leeching-torrent', [\App\Http\Controllers\UserController::class, 'leechingTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-finished-torrent', [\App\Http\Controllers\UserController::class, 'finishedTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-not-finished-torrent', [\App\Http\Controllers\UserController::class, 'notFinishedTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));

        Route::apiResource('messages', \App\Http\Controllers\MessageController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_LIST));
        Route::apiResource('messages', \App\Http\Controllers\MessageController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_STORE));
        Route::apiResource('messages', \App\Http\Controllers\MessageController::class)->only(['show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_SHOW));
        Route::apiResource('messages', \App\Http\Controllers\MessageController::class)->only(['update'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_UPDATE));
        Route::apiResource('messages', \App\Http\Controllers\MessageController::class)->only(['destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_DESTROY));
        Route::get('messages-unread', [\App\Http\Controllers\MessageController::class, 'listUnread'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_UNREAD));

        Route::get('sections', [\App\Http\Controllers\UploadController::class, 'sections'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_UPLOAD));
        Route::get('torrents/{section?}', [\App\Http\Controllers\TorrentController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_LIST));
        Route::post('upload', [\App\Http\Controllers\TorrentController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_UPLOAD));
        Route::get('detail/{id}', [\App\Http\Controllers\TorrentController::class, 'show'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_VIEW));
        Route::get('search-box', [\App\Http\Controllers\TorrentController::class, 'searchBox'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_SEARCH_BOX));

        Route::get('/profile/{id?}', [\App\Http\Controllers\UserController::class, 'show'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_VIEW));

        Route::post('bookmarks', [\App\Http\Controllers\BookmarkController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::BOOKMARK_STORE));
        Route::post('bookmarks/delete', [\App\Http\Controllers\BookmarkController::class, 'destroy'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::BOOKMARK_DELETE));

        Route::get('comments', [\App\Http\Controllers\CommentController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_VIEW));
        Route::post('comments', [\App\Http\Controllers\CommentController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::COMMENT_STORE));

        Route::apiResource('peers', \App\Http\Controllers\PeerController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::PEER_LIST));
        Route::apiResource('peers', \App\Http\Controllers\PeerController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::PEER_MANAGE));

        Route::apiResource('files', \App\Http\Controllers\FileController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FILE_LIST));
        Route::apiResource('files', \App\Http\Controllers\FileController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FILE_MANAGE));

        Route::apiResource('thanks', \App\Http\Controllers\ThankController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::THANK_LIST));
        Route::apiResource('thanks', \App\Http\Controllers\ThankController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::THANK_MANAGE));

        Route::apiResource('snatches', \App\Http\Controllers\SnatchController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SNATCH_LIST));
        Route::apiResource('snatches', \App\Http\Controllers\SnatchController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SNATCH_MANAGE));

        Route::apiResource('news', \App\Http\Controllers\NewsController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_LIST));
        Route::apiResource('news', \App\Http\Controllers\NewsController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_MANAGE));
        Route::get('news-latest', [\App\Http\Controllers\NewsController::class, 'latest'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_LATEST));

        Route::get('attend', [\App\Http\Controllers\AttendanceController::class, 'attend'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::ATTENDANCE_ATTEND));

        Route::apiResource('polls', \App\Http\Controllers\PollController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_LIST));
        Route::apiResource('polls', \App\Http\Controllers\PollController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_MANAGE));
        Route::get('polls-latest', [\App\Http\Controllers\PollController::class, 'latest'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_LATEST));
        Route::post('polls-vote', [\App\Http\Controllers\PollController::class, 'vote'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_VOTE));

        Route::apiResource('rewards', \App\Http\Controllers\RewardController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::REWARD_LIST));
        Route::apiResource('rewards', \App\Http\Controllers\RewardController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::REWARD_MANAGE));

        Route::get('notifications', [\App\Http\Controllers\ToolController::class, 'notifications'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::NOTIFICATION_LIST));

        Route::apiResource('over-forums', \App\Http\Controllers\OverForumController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::OVER_FORUM_LIST));
        Route::apiResource('over-forums', \App\Http\Controllers\OverForumController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::OVER_FORUM_MANAGE));

        Route::apiResource('forums', \App\Http\Controllers\ForumController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FORUM_LIST));
        Route::apiResource('forums', \App\Http\Controllers\ForumController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FORUM_MANAGE));

        Route::apiResource('topics', \App\Http\Controllers\TopicController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::apiResource('topics', \App\Http\Controllers\TopicController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_MANAGE));

        Route::get('shoutbox', [\App\Http\Controllers\ShoutboxController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::SHOUTBOX_LIST));

        Route::get('offers', [\App\Http\Controllers\OfferController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::OFFER_LIST));

        Route::match(['get', 'post'], 'usercp/settings', [\App\Http\Controllers\UsercpController::class, 'settings'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USERCP_SETTINGS));

    });

    Route::group(['middleware' => ['checkUserStatus']], function () {
        Route::apiResource('agent-allows', \App\Http\Controllers\AgentAllowController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_LIST));
        Route::apiResource('agent-allows', \App\Http\Controllers\AgentAllowController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_MANAGE));
        Route::get('all-agent-allows', [\App\Http\Controllers\AgentAllowController::class, 'all'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_ALL));
        Route::post('agent-check', [\App\Http\Controllers\AgentAllowController::class, 'check'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_CHECK));

        Route::apiResource('agent-denies', \App\Http\Controllers\AgentDenyController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_DENY_LIST));
        Route::apiResource('agent-denies', \App\Http\Controllers\AgentDenyController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_DENY_MANAGE));

        Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_VIEW));
        Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_STORE));
        Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['update'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_UPDATE));
        Route::apiResource('users', \App\Http\Controllers\UserController::class)->only(['destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_DESTROY));
        Route::get('user-base', [\App\Http\Controllers\UserController::class, 'base'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_BASE));
        Route::get('user-classes', [\App\Http\Controllers\UserController::class, 'classes'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_CLASSES));
        Route::get('user-invite-info', [\App\Http\Controllers\UserController::class, 'inviteInfo'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_INVITE_INFO));
        Route::get('user-match-exams', [\App\Http\Controllers\UserController::class, 'matchExams'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MATCH_EXAMS));
        Route::get('user-mod-comment', [\App\Http\Controllers\UserController::class, 'modComment'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MOD_COMMENT));
        Route::post('user-disable', [\App\Http\Controllers\UserController::class, 'disable'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_DISABLE));
        Route::post('user-enable', [\App\Http\Controllers\UserController::class, 'enable'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_ENABLE));
        Route::post('user-reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_RESET_PASSWORD));
        Route::put('user-increment-decrement', [\App\Http\Controllers\UserController::class, 'incrementDecrement'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_INCREMENT_DECREMENT));
        Route::put('user-remove-two-step', [\App\Http\Controllers\UserController::class, 'removeTwoStepAuthentication'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_REMOVE_TWO_STEP));

        Route::apiResource('exams', \App\Http\Controllers\ExamController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_LIST));
        Route::apiResource('exams', \App\Http\Controllers\ExamController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_MANAGE));
        Route::get('exams-all', [\App\Http\Controllers\ExamController::class, 'all'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_ALL));
        Route::get('exam-indexes', [\App\Http\Controllers\ExamController::class, 'indexes'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_INDEXES));

        Route::apiResource('exam-users', \App\Http\Controllers\ExamUserController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_LIST));
        Route::apiResource('exam-users', \App\Http\Controllers\ExamUserController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_MANAGE));
        Route::put('exam-users-avoid', [\App\Http\Controllers\ExamUserController::class, 'avoid'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_AVOID));
        Route::put('exam-users-recover', [\App\Http\Controllers\ExamUserController::class, 'recover'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_RECOVER));
        Route::put('exam-users-avoid-bulk', [\App\Http\Controllers\ExamUserController::class, 'bulkAvoid'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_AVOID_BULK));
        Route::put('exam-users-delete-bulk', [\App\Http\Controllers\ExamUserController::class, 'bulkDelete'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_DELETE_BULK));

        Route::get('dashboard/system-info', [\App\Http\Controllers\DashboardController::class, 'systemInfo'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/stat-data', [\App\Http\Controllers\DashboardController::class, 'statData'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/latest-user', [\App\Http\Controllers\DashboardController::class, 'latestUser'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/latest-torrent', [\App\Http\Controllers\DashboardController::class, 'latestTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));

        Route::apiResource('settings', \App\Http\Controllers\SettingController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SETTING_LIST));
        Route::apiResource('settings', \App\Http\Controllers\SettingController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SETTING_MANAGE));
        Route::apiResource('medals', \App\Http\Controllers\MedalController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MEDAL_LIST));
        Route::apiResource('medals', \App\Http\Controllers\MedalController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MEDAL_MANAGE));
        Route::apiResource('user-medals', \App\Http\Controllers\UserMedalController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MEDAL_LIST));
        Route::apiResource('user-medals', \App\Http\Controllers\UserMedalController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MEDAL_MANAGE));
        Route::apiResource('tags', \App\Http\Controllers\TagController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TAG_LIST));
        Route::apiResource('tags', \App\Http\Controllers\TagController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TAG_MANAGE));
        Route::apiResource('hr', \App\Http\Controllers\HitAndRunController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_LIST));
        Route::apiResource('hr', \App\Http\Controllers\HitAndRunController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_MANAGE));
        Route::get('hr-status', [\App\Http\Controllers\HitAndRunController::class, 'listStatus'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_LIST_STATUS));
        Route::put('hr-pardon/{id}', [\App\Http\Controllers\HitAndRunController::class, 'pardon'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_PARDON));
        Route::put('hr-delete', [\App\Http\Controllers\HitAndRunController::class, 'bulkDelete'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_BULK_DELETE));
        Route::put('hr-pardon', [\App\Http\Controllers\HitAndRunController::class, 'bulkPardon'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_BULK_PARDON));
    });

});

Route::post('login', [\App\Http\Controllers\AuthenticateController::class, 'login']);
