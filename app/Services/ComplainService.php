<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Complain;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\Logger;
use App\Support\Url;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusLock;

/**
 * Handles complain (support ticket) mutations: creating new complains,
 * replying to existing ones, and toggling answered state.
 *
 * Read-side (listing, viewing) stays in SupportController.
 */
final class ComplainService
{
    public function __construct(
        private readonly ToolRepository $toolRepository,
    ) {}

    /**
     * Create a new complain from a disabled user.
     *
     * @return string|null The complain UUID on success, null on failure.
     */
    public function createComplain(string $email, string $body, string $clientIp): ?string
    {
        try {
            NexusLock::lockOrFail('complains:lock:'.$clientIp, 10);
        } catch (\Throwable) {
            return null;
        }

        try {
            NexusLock::lockOrFail('complains:lock:'.$email, 600);
        } catch (\Throwable) {
            return null;
        }

        $user = User::query()->where('email', $email)->where('enabled', 'no')->first();
        if (! $user) {
            return null;
        }

        $complainId = (int) DB::table('complains')->insertGetId([
            'uuid' => DB::raw('UUID()'),
            'email' => $email,
            'body' => $body,
            'added' => now()->toDateTimeString(),
            'ip' => $clientIp,
        ]);

        $this->clearCountCache();

        return (string) Complain::query()->where('id', $complainId)->value('uuid');
    }

    /**
     * Reply to an existing complain.
     *
     * @param  array<string, mixed>  $langComplains
     */
    public function replyToComplain(int $complainId, int $userId, string $body, string $clientIp, array $langComplains): bool
    {
        $complain = Complain::query()->find($complainId);
        if (! $complain) {
            return false;
        }

        DB::table('complain_replies')->insert([
            'complain' => $complainId,
            'userid' => $userId,
            'added' => now()->toDateTimeString(),
            'body' => $body,
            'ip' => $clientIp,
        ]);

        if ($userId > 0) {
            try {
                $this->toolRepository->sendMail(
                    $complain->email,
                    $langComplains['reply_notify_subject'] ?? 'Reply to your complain',
                    sprintf(
                        $langComplains['reply_notify_body'] ?? '',
                        SiteConfig::current()->basic->siteName(),
                        Url::schemeAndHost(false).'/complains.php?action=view&id='.$complain->uuid
                    )
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
        DB::table('complains')->where('id', $complainId)->update([
            'answered' => $answered ? 1 : 0,
        ]);

        $this->clearCountCache();
    }

    private function clearCountCache(): void
    {
        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('COMPLAINTS_COUNT_CACHE');
        }
    }
}
