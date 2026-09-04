<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\HitAndRunStatus;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<HitAndRun> */
class HitAndRunFactory extends Factory
{
    protected $model = HitAndRun::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $user = User::factory()->create();
        $torrent = Torrent::factory()->create();
        $snatch = Snatch::query()->create([
            'torrentid' => $torrent->id,
            'userid' => $user->id,
            'ip' => '127.0.0.1',
            'port' => 54321,
            'uploaded' => 0,
            'downloaded' => 0,
            'to_go' => 0,
            'seedtime' => 0,
            'leechtime' => 0,
            'startdat' => now()->toDateTimeString(),
            'last_action' => now()->toDateTimeString(),
            'finished' => true,
        ]);

        return [
            'uid' => $user->id,
            'snatched_id' => $snatch->id,
            'torrent_id' => $torrent->id,
            'status' => HitAndRunStatus::INSPECTING->value,
            'comment' => '',
        ];
    }
}
