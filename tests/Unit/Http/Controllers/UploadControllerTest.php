<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\UploadController;
use App\Models\SearchBox;
use App\Repositories\SearchBoxRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class UploadControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sections_returns_authorized_sections(): void
    {
        $searchBox = new SearchBox;
        $searchBox->id = 1;
        $searchBox->name = 'Movies';
        $searchBox->showsubcat = 0;

        $collection = new Collection([$searchBox]);

        /** @var SearchBoxRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SearchBoxRepository::class);
        $repository->shouldReceive('listSections')
            ->once()
            ->with(Mockery::any())
            ->andReturn($collection);

        $controller = new UploadController($repository);
        $request = Request::create('/api/v1/upload/sections', 'GET', []);

        $result = $controller->sections($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_sections_returns_empty_when_no_authorized_sections(): void
    {
        /** @var SearchBoxRepository&Mockery\MockInterface $repository */
        $repository = Mockery::mock(SearchBoxRepository::class);
        $repository->shouldReceive('listSections')
            ->once()
            ->andReturn(new Collection([]));

        $controller = new UploadController($repository);
        $request = Request::create('/api/v1/upload/sections', 'GET', []);

        $result = $controller->sections($request);

        $this->assertSame(0, $result['ret']);
        $this->assertArrayHasKey('data', $result);
    }
}
