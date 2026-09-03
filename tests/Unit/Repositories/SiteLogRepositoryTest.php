<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\SiteLogRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SiteLogRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private SiteLogRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('sitelog')->delete();
        $this->repository = new SiteLogRepository;
    }

    public function test_create_with_defaults_inserts_record(): void
    {
        $this->repository->create('something happened');

        $this->assertSame(1, DB::table('sitelog')->count());

        $record = DB::table('sitelog')->first();

        $this->assertNotNull($record);
        $this->assertSame('something happened', $record->txt);
        $this->assertSame('normal', $record->security_level);
        $this->assertSame(0, (int) $record->uid);
        $this->assertNotNull($record->added);
    }

    public function test_create_with_custom_security_and_user_id(): void
    {
        $this->repository->create('mod action', 'mod', 42);

        $record = DB::table('sitelog')->first();

        $this->assertNotNull($record);
        $this->assertSame('mod action', $record->txt);
        $this->assertSame('mod', $record->security_level);
        $this->assertSame(42, (int) $record->uid);
    }

    public function test_create_with_null_user_id_defaults_to_zero(): void
    {
        $this->repository->create('no user', 'normal', null);

        $record = DB::table('sitelog')->first();

        $this->assertNotNull($record);
        $this->assertSame(0, (int) $record->uid);
    }
}
