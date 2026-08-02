<?php

namespace Tests\Unit\Policies;

use App\Models\Torrent;
use App\Models\User;
use App\Policies\TorrentPolicy;
use PHPUnit\Framework\TestCase;

final class TorrentPolicyTest extends TestCase
{
    public function test_download_is_denied_when_user_has_no_download_position(): void
    {
        $user = new User(['downloadpos' => 'no']);
        $torrent = new Torrent();
        $policy = new TorrentPolicy();

        $this->assertFalse($policy->download($user, $torrent));
    }
}
