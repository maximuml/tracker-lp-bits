<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\LocationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Unit tests for LocationService.
 *
 * Covers createLocation (validation + insert), updateLocation
 * (validation + update), deleteLocation, and findLocation.
 */
final class LocationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('locations')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new LocationService;
    }

    /** @return array<string, string> */
    private function validData(): array
    {
        return [
            'name' => 'Test Location',
            'flagpic' => 'test.png',
            'location_main' => 'Main',
            'location_sub' => 'Sub',
            'start_ip' => '192.168.1.1',
            'end_ip' => '192.168.1.100',
            'theory_upspeed' => '100',
            'practical_upspeed' => '90',
            'theory_downspeed' => '200',
            'practical_downspeed' => '180',
        ];
    }

    // --- createLocation ---

    public function test_create_location_with_valid_data(): void
    {
        $result = $this->service->createLocation($this->validData());

        $this->assertTrue($result);
        $this->assertSame(1, DB::table('locations')->count());
        $row = DB::table('locations')->first();
        $this->assertNotNull($row);
        $this->assertSame('Test Location', $row->name);
        $this->assertSame('192.168.1.1', $row->start_ip);
        $this->assertSame('192.168.1.100', $row->end_ip);
    }

    public function test_create_location_rejects_invalid_start_ip(): void
    {
        $data = $this->validData();
        $data['start_ip'] = 'not-an-ip';

        $result = $this->service->createLocation($data);

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('locations')->count());
    }

    public function test_create_location_rejects_invalid_end_ip(): void
    {
        $data = $this->validData();
        $data['end_ip'] = '999.999.999.999';

        $result = $this->service->createLocation($data);

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('locations')->count());
    }

    public function test_create_location_rejects_end_before_start(): void
    {
        $data = $this->validData();
        $data['start_ip'] = '192.168.1.100';
        $data['end_ip'] = '192.168.1.1';

        $result = $this->service->createLocation($data);

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('locations')->count());
    }

    public function test_create_location_rejects_equal_start_and_end(): void
    {
        $data = $this->validData();
        $data['start_ip'] = '10.0.0.1';
        $data['end_ip'] = '10.0.0.1';

        $result = $this->service->createLocation($data);

        $this->assertFalse($result);
        $this->assertSame(0, DB::table('locations')->count());
    }

    public function test_create_location_with_empty_data_uses_defaults(): void
    {
        // Empty data — start_ip and end_ip are empty strings, which are
        // not valid IPv4, so this should fail
        $result = $this->service->createLocation([]);

        $this->assertFalse($result);
    }

    // --- updateLocation ---

    public function test_update_location_with_valid_data(): void
    {
        $id = $this->service->createLocation($this->validData());
        $this->assertTrue($id);

        $locationId = (int) DB::table('locations')->value('id');

        $data = $this->validData();
        $data['name'] = 'Updated Name';

        $result = $this->service->updateLocation($locationId, $data);

        $this->assertTrue($result);
        $row = DB::table('locations')->where('id', $locationId)->first();
        $this->assertNotNull($row);
        $this->assertSame('Updated Name', $row->name);
    }

    public function test_update_location_rejects_invalid_ip(): void
    {
        $this->service->createLocation($this->validData());
        $locationId = (int) DB::table('locations')->value('id');

        $data = $this->validData();
        $data['start_ip'] = 'invalid';

        $result = $this->service->updateLocation($locationId, $data);

        $this->assertFalse($result);
    }

    public function test_update_location_rejects_end_before_start(): void
    {
        $this->service->createLocation($this->validData());
        $locationId = (int) DB::table('locations')->value('id');

        $data = $this->validData();
        $data['start_ip'] = '192.168.1.200';
        $data['end_ip'] = '192.168.1.100';

        $result = $this->service->updateLocation($locationId, $data);

        $this->assertFalse($result);
    }

    // --- deleteLocation ---

    public function test_delete_location_removes_row(): void
    {
        $this->service->createLocation($this->validData());
        $locationId = (int) DB::table('locations')->value('id');

        $this->service->deleteLocation($locationId);

        $this->assertSame(0, DB::table('locations')->count());
    }

    public function test_delete_nonexistent_location_does_not_throw(): void
    {
        // Should not throw
        $this->service->deleteLocation(99999);
        $this->expectNotToPerformAssertions();
    }

    // --- findLocation ---

    public function test_find_location_returns_row(): void
    {
        $this->service->createLocation($this->validData());
        $locationId = (int) DB::table('locations')->value('id');

        $row = $this->service->findLocation($locationId);

        $this->assertNotNull($row);
        $this->assertSame('Test Location', $row['name']);
        $this->assertSame('192.168.1.1', $row['start_ip']);
    }

    public function test_find_location_returns_null_for_nonexistent(): void
    {
        $row = $this->service->findLocation(99999);

        $this->assertNull($row);
    }
}
