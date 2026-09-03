<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\CountryRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CountryRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CountryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CountryRepository;
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $this->assertNull($this->repository->findById(99999));
    }

    public function test_find_by_id_returns_array_when_found(): void
    {
        $id = (int) DB::table('countries')->insertGetId([
            'name' => 'Test Country',
            'flagpic' => 'test.gif',
        ]);

        $result = $this->repository->findById($id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
        $this->assertSame('Test Country', $result['name']);
        $this->assertSame('test.gif', $result['flagpic']);
    }

    public function test_find_by_id_accepts_string_id(): void
    {
        $id = (int) DB::table('countries')->insertGetId([
            'name' => 'String Id Country',
            'flagpic' => 'flag.png',
        ]);

        $result = $this->repository->findById((string) $id);

        $this->assertNotNull($result);
        $this->assertSame($id, (int) $result['id']);
    }
}
