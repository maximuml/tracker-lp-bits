<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ComplainRepositoryInterface;
use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Repositories\UserAccountRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Lock;
use App\Support\Logger;
use App\Support\Url;
use Illuminate\Support\Facades\DB;

/**
 * Handles complain (support ticket) mutations: creating new complains,
 * replying to existing ones, and toggling answered state.
 *
 * Read-side (listing, viewing) stays in SupportController.
 */
final class ComplainService
{
    public function __construct(
        private readonly ComplainRepositoryInterface $complainRepository,
        private readonly ToolRepositoryInterface $toolRepository,
        private readonly UserAccountRepository $userAccountRepository,
        private readonly ?LegacyRedisCache $legacyRedisCache = null,
    ) {}

    /**
     * Create a new complain from a disabled user.
     *
     * @return string|null The complain UUID on success, null on failure.
     */
    public function createComplain(string $email, string $body, string $clientIp): ?string
    {
        try {
            Lock::lockOrFail('complains:lock:'.$clientIp, 10);
        } catch (\Throwable) {
            return null;
        }

        try {
            Lock::lockOrFail('complains:lock:'.$email, 600);
        } catch (\Throwable) {
            return null;
        }

        $user = $this->userAccountRepository->findDisabledByEmail($email);
        if (! $user) {
            return null;
        }

        $complainId = $this->complainRepository->insertComplain([
            'uuid' => DB::raw('UUID()'),
            'email' => $email,
            'body' => $body,
            'added' => now()->toDateTimeString(),
            'ip' => $clientIp,
        ]);

        $this->clearCountCache();

        return (string) $this->complainRepository->getUuidById($complainId);
    }

    /**
     * Reply to an existing complain.
     */
    public function replyToComplain(int $complainId, int $userId, string $body, string $clientIp): bool
    {
        $complain = $this->complainRepository->findById($complainId);
        if (! $complain) {
            return false;
        }

        $this->complainRepository->insertReply([
            'complain' => $complainId,
            'userid' => $userId,
            'added' => now()->toDateTimeString(),
            'body' => $body,
            'ip' => $clientIp,
        ]);

        if ($userId > 0) {
            try {
                $this->toolRepository->sendMail(
                    $complain['email'],
                    __('complains.reply_notify_subject'),
                    view('emails.complain-reply', [
                        'siteName' => SiteConfig::current()->basic->siteName(),
                        'url' => Url::schemeAndHost(false).'/web/complains?action=view&id='.$complain['uuid'],
                    ])->render()
                );
            } catch (\Throwable $exception) {
                Logger::writeWithContext((string) $exception->getMessage(), 'error', false);
            }
        }

        return true;
    }

    /**
     * Toggle the answered state of a complain. Admin only.
     */
    public function toggleAnswered(int $complainId, bool $answered): void
    {
        $this->complainRepository->updateById($complainId, [
            'answered' => $answered ? 1 : 0,
        ]);

        $this->clearCountCache();
    }

    private function clearCountCache(): void
    {
        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('COMPLAINTS_COUNT_CACHE');
        }
    }
}
