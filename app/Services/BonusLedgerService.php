<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BusinessType;
use App\Models\BonusLogs;
use App\Models\User;
use App\Repositories\BonusConsumptionRepository;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\QueryLog;
use Illuminate\Support\Facades\DB;

/**
 * Single transactional contract for seedbonus balance changes.
 *
 * Every balance mutation in the codebase — web bonus-shop purchases,
 * magic-award transfers, torrent sales, API reward endpoints — must
 * funnel through debit()/credit()/transfer() so that locking, atomic
 * updates, bonus_log writes and cache invalidation stay identical no
 * matter which entry point triggered the change.
 *
 * Invariants enforced here:
 *  - every debit/credit happens under `lockForUpdate` on the users row;
 *  - `transfer` locks both balance rows in ascending-id order so
 *    opposing transfers cannot ABBA-deadlock;
 *  - each movement writes exactly one bonus_logs row carrying the
 *    locked before/after balances;
 *  - the per-user cache is invalidated inside the same transaction.
 */
final class BonusLedgerService
{
    public function __construct(
        private readonly BonusConsumptionRepository $consumptionRepository,
    ) {}

    /**
     * Debit `seedbonus` from a user: row lock, balance check, CAS
     * update, bonus_log entry, cache clear.
     *
     * @param  array<string, mixed>  $userUpdates  Extra atomic column
     *                                             updates applied inside the same CAS write (e.g. invites).
     *
     * @throws \LogicException When the balance is insufficient.
     */
    public function debit(User|int $user, float $amount, BusinessType $type, string $comment = '', array $userUpdates = []): void
    {
        $this->consumptionRepository->consumeUserBonus($user, $amount, $type->value, $comment, $userUpdates);
    }

    /**
     * Credit `seedbonus` to a user: row lock, atomic increment,
     * bonus_log entry, cache clear. The credit-side counterpart of
     * {@see debit()} — previously callers hand-rolled
     * `increment('seedbonus')` + `BonusLogs::insert()` pairs without
     * any locking.
     *
     * @throws \InvalidArgumentException When the user does not exist.
     */
    public function credit(int $userId, float $amount, BusinessType $type, string $comment = ''): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }
        $this->applyDelta($userId, (string) $amount, $type, $comment, strict: true);
    }

    /**
     * Tolerant accrual for "earn points" flows (thanks, votes, forum
     * posts, comments): honors the bonus tweak gate like the retired
     * `Bonus::updatePoints()` did, no-ops on zero/negative amounts and
     * silently skips deleted users — while still writing the bonus_logs
     * audit row and clearing the user cache, which the legacy path
     * never did.
     */
    public function award(int $userId, float $amount, BusinessType $type, string $comment = '', ?string $bonusTweak = null): void
    {
        if ($amount <= 0 || ! $this->bonusPointsActive($bonusTweak)) {
            return;
        }
        $this->applyDelta($userId, (string) $amount, $type, $comment, strict: false);
    }

    /**
     * Tolerant clawback for moderation reversals (deleted post/topic/
     * comment/torrent): same tweak gate and missing-user tolerance as
     * {@see award()}, and like the legacy bare `seedbonus - X` update
     * it does NOT enforce a non-negative balance — a revoked bonus
     * larger than the current balance leaves the account in debt.
     */
    public function revoke(int $userId, float $amount, BusinessType $type, string $comment = '', ?string $bonusTweak = null): void
    {
        if ($amount <= 0 || ! $this->bonusPointsActive($bonusTweak)) {
            return;
        }
        $this->applyDelta($userId, (string) (-$amount), $type, $comment, strict: false);
    }

    /**
     * Atomically move bonus between two users. Both balance rows are
     * locked in ascending-id order (the canonical ordering shared by
     * every multi-party path) before the sender is debited and the
     * recipient credited — so two users transferring to each other
     * concurrently serialize instead of deadlocking, and a failed
     * debit rolls the credit back with it.
     *
     * `$creditAmount` defaults to `$debitAmount`; they differ when a
     * tax/fee is skimmed off the received side (e.g. bonus gifts).
     *
     * @throws \LogicException When the sender's balance is insufficient.
     */
    public function transfer(
        int $fromUserId,
        int $toUserId,
        float $debitAmount,
        BusinessType $debitType,
        BusinessType $creditType,
        ?float $creditAmount = null,
        string $debitComment = '',
        string $creditComment = '',
    ): void {
        if ($fromUserId === $toUserId) {
            throw new \LogicException('Cannot transfer bonus to yourself.');
        }
        $creditAmount ??= $debitAmount;

        DB::transaction(function () use ($fromUserId, $toUserId, $debitAmount, $creditAmount, $debitType, $creditType, $debitComment, $creditComment) {
            // Acquire both row locks in ascending-id order before any
            // nested debit()/credit() re-takes them — the canonical
            // order prevents ABBA deadlocks between opposing transfers.
            $locked = DB::table('users')
                ->whereIn('id', [$fromUserId, $toUserId])
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id')
                ->all();
            if (! in_array($fromUserId, $locked, true)) {
                throw new \LogicException('Sender not found.');
            }
            if (! in_array($toUserId, $locked, true)) {
                throw new \LogicException('Recipient not found.');
            }

            $this->debit($fromUserId, $debitAmount, $debitType, $debitComment);
            $this->credit($toUserId, $creditAmount, $creditType, $creditComment);
        });
    }

    /**
     * Shared locked-delta core for credit()/award()/revoke(): row lock,
     * CAS update on the observed balance, bonus_logs row, cache clear —
     * all inside one transaction. `$strict` controls the missing-user
     * contract: strict callers get InvalidArgumentException, tolerant
     * callers (legacy accrual semantics) get a silent no-op.
     *
     * @param  numeric-string  $delta
     */
    private function applyDelta(int $userId, string $delta, BusinessType $type, string $comment, bool $strict): void
    {
        DB::transaction(function () use ($userId, $delta, $type, $comment, $strict) {
            $lockedUser = DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first(['id', 'seedbonus', 'passkey']);
            if ($lockedUser === null) {
                if ($strict) {
                    throw new \InvalidArgumentException('User not found');
                }

                return;
            }

            $oldUserBonus = (float) ($lockedUser->seedbonus ?? 0);
            // Scale 10 keeps fractional deltas (e.g. saythanks 0.5):
            // with the default bcmath.scale=0 the sum silently
            // truncated to integer, produced a no-op UPDATE and tripped
            // the affected-rows check below. The decimal(20,1) target
            // columns round the result identically on write.
            $newUserBonus = bcadd((string) $oldUserBonus, $delta, 10);
            $affectedRows = DB::table('users')
                ->where('id', $userId)
                ->where('seedbonus', $oldUserBonus)
                ->update(['seedbonus' => $newUserBonus]);
            if ($affectedRows != 1) {
                Logger::writeWithContext((string) ('applyDelta user seedbonus affected rows: '.$affectedRows.' != 1, query: '.QueryLog::last()), 'error', false);
                throw new \RuntimeException('Update user seedbonus fail.');
            }
            $nowStr = now()->toDateTimeString();
            $bonusLog = [
                'business_type' => $type->value,
                'uid' => $userId,
                'old_total_value' => $oldUserBonus,
                'value' => abs((float) $delta),
                'new_total_value' => $newUserBonus,
                'comment' => sprintf('[%s] %s', $type->label(), $comment),
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            BonusLogs::query()->insert($bonusLog);
            Cache::clearUser($userId, (string) ($lockedUser->passkey ?? ''));
        });
    }

    private function bonusPointsActive(?string $bonusTweak): bool
    {
        $bonusTweak ??= SiteConfig::current()->tweak->bonus();

        return $bonusTweak === 'enable' || $bonusTweak === 'disablesave';
    }
}
