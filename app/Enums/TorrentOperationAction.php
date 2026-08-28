<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Backed enum for torrent operation log action types.
 *
 * Mirrors the string constants from App\Models\TorrentOperationLog:
 *   ACTION_TYPE_APPROVAL_NONE, ACTION_TYPE_APPROVAL_ALLOW,
 *   ACTION_TYPE_APPROVAL_DENY, ACTION_TYPE_EDIT, ACTION_TYPE_DELETE.
 */
enum TorrentOperationAction: string
{
    case APPROVAL_NONE = 'approval_none';
    case APPROVAL_ALLOW = 'approval_allow';
    case APPROVAL_DENY = 'approval_deny';
    case EDIT = 'edit';
    case DELETE = 'delete';

    public function label(): string
    {
        return match ($this) {
            self::APPROVAL_NONE => 'Approval: None',
            self::APPROVAL_ALLOW => 'Approval: Allow',
            self::APPROVAL_DENY => 'Approval: Deny',
            self::EDIT => 'Edit',
            self::DELETE => 'Delete',
        };
    }

    public static function fromStringSafe(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::APPROVAL_NONE;
    }
}
