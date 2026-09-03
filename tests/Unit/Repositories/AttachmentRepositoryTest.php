<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\AttachmentRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class AttachmentRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private AttachmentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AttachmentRepository;
    }

    public function test_find_by_dlkey_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findByDlkey('nonexistentkey123456789012'));
    }

    public function test_find_by_dlkey_returns_array_when_found(): void
    {
        $dlkey = str_pad('dlkey1', 32, '0');
        $id = (int) DB::table('attachments')->insertGetId([
            'userid' => 5,
            'width' => 100,
            'added' => now()->toDateTimeString(),
            'filename' => 'photo.jpg',
            'dlkey' => $dlkey,
            'filetype' => 'jpg',
            'filesize' => 2048,
            'location' => 'attachments/photo.jpg',
            'downloads' => 0,
            'isimage' => 1,
            'thumb' => 0,
            'driver' => 'local',
        ]);

        $result = $this->repository->findByDlkey($dlkey);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('photo.jpg', $result['filename']);
        $this->assertSame($dlkey, $result['dlkey']);
    }

    public function test_find_by_dlkeys_returns_empty_array_for_empty_input(): void
    {
        $this->assertSame([], $this->repository->findByDlkeys([]));
    }

    public function test_find_by_dlkeys_returns_keyed_by_dlkey(): void
    {
        $dlkey1 = str_pad('dlkeyA', 32, '0');
        $dlkey2 = str_pad('dlkeyB', 32, '0');

        DB::table('attachments')->insert([
            ['userid' => 1, 'width' => 0, 'added' => now()->toDateTimeString(), 'filename' => 'a.txt', 'dlkey' => $dlkey1, 'filetype' => 'txt', 'filesize' => 10, 'location' => 'a', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
            ['userid' => 2, 'width' => 0, 'added' => now()->toDateTimeString(), 'filename' => 'b.txt', 'dlkey' => $dlkey2, 'filetype' => 'txt', 'filesize' => 20, 'location' => 'b', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
        ]);

        $result = $this->repository->findByDlkeys([$dlkey1, $dlkey2]);

        $this->assertCount(2, $result);
        $this->assertArrayHasKey($dlkey1, $result);
        $this->assertArrayHasKey($dlkey2, $result);
        $this->assertSame('a.txt', $result[$dlkey1]['filename']);
        $this->assertSame('b.txt', $result[$dlkey2]['filename']);
    }

    public function test_find_by_dlkeys_ignores_non_matching_keys(): void
    {
        $dlkey = str_pad('dlkeyC', 32, '0');
        DB::table('attachments')->insert([
            'userid' => 1, 'width' => 0, 'added' => now()->toDateTimeString(), 'filename' => 'c.txt', 'dlkey' => $dlkey, 'filetype' => 'txt', 'filesize' => 10, 'location' => 'c', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local',
        ]);

        $result = $this->repository->findByDlkeys([$dlkey, str_pad('missing', 32, '0')]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey($dlkey, $result);
    }

    public function test_count_recent_for_user_returns_zero_when_no_records(): void
    {
        $this->assertSame(0, $this->repository->countRecentForUser(123));
    }

    public function test_count_recent_for_user_counts_only_recent_for_given_user(): void
    {
        $recent = now()->toDateTimeString();
        $old = now()->subDays(2)->toDateTimeString();

        DB::table('attachments')->insert([
            ['userid' => 10, 'width' => 0, 'added' => $recent, 'filename' => 'r1.txt', 'dlkey' => str_pad('r1', 32, '0'), 'filetype' => 'txt', 'filesize' => 1, 'location' => 'r1', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
            ['userid' => 10, 'width' => 0, 'added' => $recent, 'filename' => 'r2.txt', 'dlkey' => str_pad('r2', 32, '0'), 'filetype' => 'txt', 'filesize' => 1, 'location' => 'r2', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
            ['userid' => 10, 'width' => 0, 'added' => $old, 'filename' => 'o1.txt', 'dlkey' => str_pad('o1', 32, '0'), 'filetype' => 'txt', 'filesize' => 1, 'location' => 'o1', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
            ['userid' => 99, 'width' => 0, 'added' => $recent, 'filename' => 'x1.txt', 'dlkey' => str_pad('x1', 32, '0'), 'filetype' => 'txt', 'filesize' => 1, 'location' => 'x1', 'downloads' => 0, 'isimage' => 0, 'thumb' => 0, 'driver' => 'local'],
        ]);

        $this->assertSame(2, $this->repository->countRecentForUser(10));
    }
}
