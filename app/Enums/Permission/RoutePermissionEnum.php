<?php

namespace App\Enums\Permission;

/**
 * API route ability scopes.
 *
 * Each case is a token ability used by the Sanctum `ability` middleware.
 * The optional `toPermissionEnum()` mapping connects an API scope to the
 * legacy/class/role permission system where applicable.
 */
enum RoutePermissionEnum: string
{
    /* Torrents */
    case TORRENT_LIST = 'torrent:list';
    case TORRENT_VIEW = 'torrent:view';
    case TORRENT_UPLOAD = 'torrent:upload';
    case TORRENT_SEARCH_BOX = 'torrent:search_box';

    /* Authentication */
    case AUTH_LOGOUT = 'auth:logout';

    /* Users (profile / self) */
    case USER_ME = 'user:me';
    case USER_VIEW = 'user:view';
    case USER_TORRENTS = 'user:torrents';
    case USER_CLASSES = 'user:classes';
    case USER_BASE = 'user:base';
    case USER_INVITE_INFO = 'user:invite_info';
    case USER_MATCH_EXAMS = 'user:match_exams';
    case USER_MOD_COMMENT = 'user:mod_comment';
    case USER_DISABLE = 'user:disable';
    case USER_ENABLE = 'user:enable';
    case USER_RESET_PASSWORD = 'user:reset_password';
    case USER_INCREMENT_DECREMENT = 'user:increment_decrement';
    case USER_REMOVE_TWO_STEP = 'user:remove_two_step';
    case USER_STORE = 'user:store';
    case USER_UPDATE = 'user:update';
    case USER_DESTROY = 'user:destroy';

    /* Bookmarks */
    case BOOKMARK_STORE = 'bookmark:store';
    case BOOKMARK_DELETE = 'bookmark:delete';

    /* Comments */
    case COMMENT_LIST = 'comment:list';
    case COMMENT_STORE = 'comment:store';
    case COMMENT_SHOW = 'comment:show';
    case COMMENT_UPDATE = 'comment:update';
    case COMMENT_DESTROY = 'comment:destroy';

    /* Messages */
    case MESSAGE_LIST = 'message:list';
    case MESSAGE_STORE = 'message:store';
    case MESSAGE_SHOW = 'message:show';
    case MESSAGE_UPDATE = 'message:update';
    case MESSAGE_DESTROY = 'message:destroy';
    case MESSAGE_UNREAD = 'message:unread';

    /* Peers */
    case PEER_LIST = 'peer:list';
    case PEER_MANAGE = 'peer:manage';

    /* Files */
    case FILE_LIST = 'file:list';
    case FILE_MANAGE = 'file:manage';

    /* Thanks */
    case THANK_LIST = 'thank:list';
    case THANK_MANAGE = 'thank:manage';

    /* Snatches */
    case SNATCH_LIST = 'snatch:list';
    case SNATCH_MANAGE = 'snatch:manage';

    /* News */
    case NEWS_LIST = 'news:list';
    case NEWS_MANAGE = 'news:manage';
    case NEWS_LATEST = 'news:latest';

    /* Attendance */
    case ATTENDANCE_ATTEND = 'attendance:attend';

    /* Polls */
    case POLL_LIST = 'poll:list';
    case POLL_MANAGE = 'poll:manage';
    case POLL_VOTE = 'poll:vote';
    case POLL_LATEST = 'poll:latest';

    /* Rewards */
    case REWARD_LIST = 'reward:list';
    case REWARD_MANAGE = 'reward:manage';

    /* Notifications */
    case NOTIFICATION_LIST = 'notification:list';

    /* Forums */
    case OVER_FORUM_LIST = 'over_forum:list';
    case OVER_FORUM_MANAGE = 'over_forum:manage';
    case FORUM_LIST = 'forum:list';
    case FORUM_MANAGE = 'forum:manage';
    case TOPIC_LIST = 'topic:list';
    case TOPIC_MANAGE = 'topic:manage';

    /* Shoutbox */
    case SHOUTBOX_LIST = 'shoutbox:list';

    /* Agent allow / deny */
    case AGENT_ALLOW_LIST = 'agent_allow:list';
    case AGENT_ALLOW_MANAGE = 'agent_allow:manage';
    case AGENT_ALLOW_ALL = 'agent_allow:all';
    case AGENT_ALLOW_CHECK = 'agent_allow:check';
    case AGENT_DENY_LIST = 'agent_deny:list';
    case AGENT_DENY_MANAGE = 'agent_deny:manage';

    /* Exams */
    case EXAM_LIST = 'exam:list';
    case EXAM_MANAGE = 'exam:manage';
    case EXAM_ALL = 'exam:all';
    case EXAM_INDEXES = 'exam:indexes';
    case EXAM_USER_LIST = 'exam_user:list';
    case EXAM_USER_MANAGE = 'exam_user:manage';
    case EXAM_USER_AVOID = 'exam_user:avoid';
    case EXAM_USER_RECOVER = 'exam_user:recover';
    case EXAM_USER_AVOID_BULK = 'exam_user:avoid_bulk';
    case EXAM_USER_DELETE_BULK = 'exam_user:delete_bulk';

    /* Dashboard */
    case DASHBOARD_VIEW = 'dashboard:view';

    /* Settings */
    case SETTING_LIST = 'setting:list';
    case SETTING_MANAGE = 'setting:manage';

    /* Medals */
    case MEDAL_LIST = 'medal:list';
    case MEDAL_MANAGE = 'medal:manage';
    case USER_MEDAL_LIST = 'user_medal:list';
    case USER_MEDAL_MANAGE = 'user_medal:manage';

    /* Tags */
    case TAG_LIST = 'tag:list';
    case TAG_MANAGE = 'tag:manage';

    /* Hit and run */
    case HIT_AND_RUN_LIST = 'hit_and_run:list';
    case HIT_AND_RUN_MANAGE = 'hit_and_run:manage';
    case HIT_AND_RUN_LIST_STATUS = 'hit_and_run:list_status';
    case HIT_AND_RUN_PARDON = 'hit_and_run:pardon';
    case HIT_AND_RUN_BULK_PARDON = 'hit_and_run:bulk_pardon';
    case HIT_AND_RUN_BULK_DELETE = 'hit_and_run:bulk_delete';

    /**
     * Map the API ability to the legacy permission enum, if one exists.
     */
    public function toPermissionEnum(): ?PermissionEnum
    {
        return match ($this) {
            self::TORRENT_UPLOAD => PermissionEnum::UPLOAD,
            self::TORRENT_VIEW => null,
            self::USER_VIEW => PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO,
            self::USER_TORRENTS => PermissionEnum::TORRENT_HISTORY,
            self::USER_DISABLE,
            self::USER_ENABLE,
            self::USER_RESET_PASSWORD,
            self::USER_INCREMENT_DECREMENT,
            self::USER_REMOVE_TWO_STEP,
            self::USER_STORE,
            self::USER_UPDATE,
            self::USER_DESTROY => PermissionEnum::MANAGE_USER_BASIC_INFO,
            self::COMMENT_STORE => PermissionEnum::POST_MANAGE,
            self::MESSAGE_STORE => PermissionEnum::SEND_INVITE,
            self::NEWS_MANAGE => PermissionEnum::NEWS_MANAGE,
            self::POLL_MANAGE => PermissionEnum::POLL_MANAGE,
            self::FORUM_MANAGE,
            self::OVER_FORUM_MANAGE,
            self::TOPIC_MANAGE => PermissionEnum::FORUM_MANAGE,
            self::AGENT_ALLOW_MANAGE,
            self::AGENT_DENY_MANAGE => PermissionEnum::CHR_MANAGE,
            self::EXAM_MANAGE,
            self::EXAM_USER_MANAGE,
            self::DASHBOARD_VIEW,
            self::SETTING_MANAGE,
            self::MEDAL_MANAGE,
            self::USER_MEDAL_MANAGE,
            self::TAG_MANAGE,
            self::HIT_AND_RUN_MANAGE,
            self::HIT_AND_RUN_PARDON,
            self::HIT_AND_RUN_BULK_PARDON,
            self::HIT_AND_RUN_BULK_DELETE => PermissionEnum::STAFF_MEMBER,
            default => null,
        };
    }
}
