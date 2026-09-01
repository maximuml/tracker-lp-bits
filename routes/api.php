<?php

use App\Enums\Permission\RoutePermissionEnum;
use App\Http\Controllers\AgentAllowController;
use App\Http\Controllers\AgentDenyController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\BookmarkController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamController;
use App\Http\Controllers\ExamUserController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\ForumController;
use App\Http\Controllers\HitAndRunController;
use App\Http\Controllers\MedalController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OverForumController;
use App\Http\Controllers\PeerController;
use App\Http\Controllers\PollController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\ShoutboxController;
use App\Http\Controllers\SnatchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\ThankController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TopicController;
use App\Http\Controllers\TorrentController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UsercpController;
use App\Http\Controllers\UserMedalController;
use App\Support\Permissions;
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

        Route::post('logout', [AuthenticateController::class, 'logout'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AUTH_LOGOUT));

        Route::get('user-me', [UserController::class, 'me'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_ME));
        Route::get('user-publish-torrent', [UserController::class, 'publishTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-seeding-torrent', [UserController::class, 'seedingTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-leeching-torrent', [UserController::class, 'leechingTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-finished-torrent', [UserController::class, 'finishedTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));
        Route::get('user-not-finished-torrent', [UserController::class, 'notFinishedTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_TORRENTS));

        Route::apiResource('messages', MessageController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_LIST));
        Route::apiResource('messages', MessageController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_STORE));
        Route::apiResource('messages', MessageController::class)->only(['show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_SHOW));
        Route::apiResource('messages', MessageController::class)->only(['update'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_UPDATE));
        Route::apiResource('messages', MessageController::class)->only(['destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_DESTROY));
        Route::get('messages-unread', [MessageController::class, 'listUnread'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::MESSAGE_UNREAD));

        Route::get('sections', [UploadController::class, 'sections'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_UPLOAD));
        Route::get('torrents/{section?}', [TorrentController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_LIST));
        Route::post('upload', [TorrentController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_UPLOAD));
        Route::get('detail/{id}', [TorrentController::class, 'show'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_VIEW));
        Route::get('search-box', [TorrentController::class, 'searchBox'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_SEARCH_BOX));

        Route::get('/profile/{id?}', [UserController::class, 'show'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_VIEW));

        Route::post('bookmarks', [BookmarkController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::BOOKMARK_STORE));
        Route::post('bookmarks/delete', [BookmarkController::class, 'destroy'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::BOOKMARK_DELETE));

        Route::get('comments', [CommentController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TORRENT_VIEW));
        Route::post('comments', [CommentController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::COMMENT_STORE));

        Route::apiResource('peers', PeerController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::PEER_LIST));

        Route::apiResource('files', FileController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FILE_LIST));

        Route::apiResource('thanks', ThankController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::THANK_LIST));
        Route::apiResource('thanks', ThankController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::THANK_MANAGE));

        Route::apiResource('snatches', SnatchController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SNATCH_LIST));

        Route::apiResource('news', NewsController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_LIST));
        Route::apiResource('news', NewsController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_MANAGE));
        Route::get('news-latest', [NewsController::class, 'latest'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::NEWS_LATEST));

        Route::get('attend', [AttendanceController::class, 'attend'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::ATTENDANCE_ATTEND));

        Route::apiResource('polls', PollController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_LIST));
        Route::apiResource('polls', PollController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_MANAGE));
        Route::get('polls-latest', [PollController::class, 'latest'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_LATEST));
        Route::post('polls-vote', [PollController::class, 'vote'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::POLL_VOTE));

        Route::apiResource('rewards', RewardController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::REWARD_LIST));
        Route::apiResource('rewards', RewardController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::REWARD_MANAGE));

        Route::get('notifications', [ToolController::class, 'notifications'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::NOTIFICATION_LIST));

        Route::apiResource('over-forums', OverForumController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::OVER_FORUM_LIST));

        Route::apiResource('forums', ForumController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FORUM_LIST));
        Route::apiResource('forums', ForumController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::FORUM_MANAGE));

        Route::apiResource('topics', TopicController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::apiResource('topics', TopicController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_MANAGE));

        Route::get('topics/{topic}/posts', [PostController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::post('topics/{topic}/posts', [PostController::class, 'store'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::get('topics/{topic}/posts/{post}', [PostController::class, 'show'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::match(['put', 'patch'], 'topics/{topic}/posts/{post}', [PostController::class, 'update'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));
        Route::delete('topics/{topic}/posts/{post}', [PostController::class, 'destroy'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::TOPIC_LIST));

        Route::get('shoutbox', [ShoutboxController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::SHOUTBOX_LIST));

        Route::get('offers', [OfferController::class, 'index'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::OFFER_LIST));

        Route::match(['get', 'post'], 'usercp/settings', [UsercpController::class, 'settings'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USERCP_SETTINGS));

        Route::post('usercp/forum', [UsercpController::class, 'forum'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USERCP_SETTINGS));

        Route::post('usercp/tracker', [UsercpController::class, 'tracker'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USERCP_SETTINGS));

        Route::post('usercp/security', [UsercpController::class, 'security'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USERCP_SETTINGS));

    });

    Route::group(['middleware' => ['checkUserStatus']], function () {
        Route::apiResource('agent-allows', AgentAllowController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_LIST));
        Route::apiResource('agent-allows', AgentAllowController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_MANAGE));
        Route::get('all-agent-allows', [AgentAllowController::class, 'all'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_ALL));
        Route::post('agent-check', [AgentAllowController::class, 'check'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_ALLOW_CHECK));

        Route::apiResource('agent-denies', AgentDenyController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_DENY_LIST));
        Route::apiResource('agent-denies', AgentDenyController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::AGENT_DENY_MANAGE));

        Route::apiResource('users', UserController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_VIEW));
        Route::apiResource('users', UserController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_STORE));
        Route::get('user-base', [UserController::class, 'base'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_BASE));
        Route::get('user-classes', [UserController::class, 'classes'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_CLASSES));
        Route::get('user-invite-info', [UserController::class, 'inviteInfo'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_INVITE_INFO));
        Route::get('user-match-exams', [UserController::class, 'matchExams'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MATCH_EXAMS));
        Route::get('user-mod-comment', [UserController::class, 'modComment'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MOD_COMMENT));
        Route::post('user-disable', [UserController::class, 'disable'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_DISABLE));
        Route::post('user-enable', [UserController::class, 'enable'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_ENABLE));
        Route::post('user-reset-password', [UserController::class, 'resetPassword'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_RESET_PASSWORD));
        Route::put('user-increment-decrement', [UserController::class, 'incrementDecrement'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_INCREMENT_DECREMENT));
        Route::put('user-remove-two-step', [UserController::class, 'removeTwoStepAuthentication'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_REMOVE_TWO_STEP));

        Route::apiResource('exams', ExamController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_LIST));
        Route::apiResource('exams', ExamController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_MANAGE));
        Route::get('exams-all', [ExamController::class, 'all'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_ALL));
        Route::get('exam-indexes', [ExamController::class, 'indexes'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_INDEXES));

        Route::apiResource('exam-users', ExamUserController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_LIST));
        Route::apiResource('exam-users', ExamUserController::class)->only(['store', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_MANAGE));
        Route::put('exam-users-avoid', [ExamUserController::class, 'avoid'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_AVOID));
        Route::put('exam-users-recover', [ExamUserController::class, 'recover'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_RECOVER));
        Route::put('exam-users-avoid-bulk', [ExamUserController::class, 'bulkAvoid'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_AVOID_BULK));
        Route::put('exam-users-delete-bulk', [ExamUserController::class, 'bulkDelete'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::EXAM_USER_DELETE_BULK));

        Route::get('dashboard/system-info', [DashboardController::class, 'systemInfo'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/stat-data', [DashboardController::class, 'statData'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/latest-user', [DashboardController::class, 'latestUser'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));
        Route::get('dashboard/latest-torrent', [DashboardController::class, 'latestTorrent'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::DASHBOARD_VIEW));

        Route::apiResource('settings', SettingController::class)->only(['index'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SETTING_LIST));
        Route::apiResource('settings', SettingController::class)->only(['store'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::SETTING_MANAGE));
        Route::apiResource('medals', MedalController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MEDAL_LIST));
        Route::apiResource('medals', MedalController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::MEDAL_MANAGE));
        Route::apiResource('user-medals', UserMedalController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MEDAL_LIST));
        Route::apiResource('user-medals', UserMedalController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::USER_MEDAL_MANAGE));
        Route::apiResource('tags', TagController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TAG_LIST));
        Route::apiResource('tags', TagController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::TAG_MANAGE));
        Route::apiResource('hr', HitAndRunController::class)->only(['index', 'show'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_LIST));
        Route::apiResource('hr', HitAndRunController::class)->only(['store', 'update', 'destroy'])->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_MANAGE));
        Route::get('hr-status', [HitAndRunController::class, 'listStatus'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_LIST_STATUS));
        Route::put('hr-pardon/{id}', [HitAndRunController::class, 'pardon'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_PARDON));
        Route::put('hr-delete', [HitAndRunController::class, 'bulkDelete'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_BULK_DELETE));
        Route::put('hr-pardon', [HitAndRunController::class, 'bulkPardon'])
            ->middleware(Permissions::abilityLabel(RoutePermissionEnum::HIT_AND_RUN_BULK_PARDON));
    });

});

Route::post('login', [AuthenticateController::class, 'login']);
