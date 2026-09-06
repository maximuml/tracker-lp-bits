<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OutboxEvent;
use Illuminate\Support\Facades\DB;

/**
 * T-24: Transactional Outbox service.
 *
 * Provides a simple API for recording domain events into the outbox
 * table within the current DB transaction. The dispatcher then
 * publishes them asynchronously.
 */
final class OutboxService
{
    /**
     * Record a domain event in the outbox.
     *
     * Must be called within a DB transaction so the outbox row is
     * committed atomically with the domain change.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $aggregateType,
        string $eventType,
        array $payload,
        ?int $aggregateId = null,
        ?string $eventId = null,
    ): OutboxEvent {
        return OutboxEvent::record($aggregateType, $eventType, $payload, $aggregateId, $eventId);
    }

    /**
     * Record a purchase completed event.
     *
     * @param  array<string, mixed>  $purchaseData
     */
    public function recordPurchaseCompleted(int $userId, int $torrentId, array $purchaseData = []): OutboxEvent
    {
        return $this->record(
            aggregateType: 'torrent',
            eventType: 'purchase.completed',
            payload: array_merge([
                'user_id' => $userId,
                'torrent_id' => $torrentId,
            ], $purchaseData),
            aggregateId: $torrentId,
        );
    }

    /**
     * Record a user registered event.
     *
     * @param  array<string, mixed>  $userData
     */
    public function recordUserRegistered(int $userId, array $userData = []): OutboxEvent
    {
        return $this->record(
            aggregateType: 'user',
            eventType: 'user.registered',
            payload: array_merge(['user_id' => $userId], $userData),
            aggregateId: $userId,
        );
    }

    /**
     * Record a password reset event.
     *
     * @param  array<string, mixed>  $resetData
     */
    public function recordPasswordReset(int $userId, array $resetData = []): OutboxEvent
    {
        return $this->record(
            aggregateType: 'user',
            eventType: 'password.reset',
            payload: array_merge(['user_id' => $userId], $resetData),
            aggregateId: $userId,
        );
    }

    /**
     * Record an invite consumed event.
     *
     * @param  array<string, mixed>  $inviteData
     */
    public function recordInviteConsumed(int $inviteId, int $inviterId, int $inviteeId, array $inviteData = []): OutboxEvent
    {
        return $this->record(
            aggregateType: 'invite',
            eventType: 'invite.consumed',
            payload: array_merge([
                'invite_id' => $inviteId,
                'inviter_id' => $inviterId,
                'invitee_id' => $inviteeId,
            ], $inviteData),
            aggregateId: $inviteId,
        );
    }

    /**
     * Record a moderation action event.
     *
     * @param  array<string, mixed>  $actionData
     */
    public function recordModerationAction(int $moderatorId, string $action, int $targetUserId, array $actionData = []): OutboxEvent
    {
        return $this->record(
            aggregateType: 'moderation',
            eventType: 'moderation.action',
            payload: array_merge([
                'moderator_id' => $moderatorId,
                'action' => $action,
                'target_user_id' => $targetUserId,
            ], $actionData),
            aggregateId: $targetUserId,
        );
    }
}
