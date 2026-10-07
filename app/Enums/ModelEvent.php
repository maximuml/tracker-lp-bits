<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Model-change event names published on the CHANNEL_NAME_MODEL_EVENT
 * Redis channel — the wire names external subscribers match on.
 */
enum ModelEvent: string
{
    case TorrentCreated = 'torrent_created';
    case TorrentUpdated = 'torrent_updated';
    case TorrentDeleted = 'torrent_deleted';

    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case UserDeleted = 'user_deleted';
    case UserEnabled = 'user_enabled';
    case UserDisabled = 'user_disabled';

    case NewsCreated = 'news_created';
    case SnatchedUpdated = 'snatched_updated';
    case MessageCreated = 'message_created';
    case StaffMessageCreated = 'staff_message_created';

    case HitAndRunCreated = 'hit_and_run_created';
    case HitAndRunUpdated = 'hit_and_run_updated';
    case HitAndRunDeleted = 'hit_and_run_deleted';

    case AgentAllowCreated = 'agent_allow_created';
    case AgentAllowUpdated = 'agent_allow_updated';
    case AgentAllowDeleted = 'agent_allow_deleted';

    case AgentDenyCreated = 'agent_deny_created';
    case AgentDenyUpdated = 'agent_deny_updated';
    case AgentDenyDeleted = 'agent_deny_deleted';

    case GlobalPromotionStateUpdated = 'global_promotion_state_updated';
}
