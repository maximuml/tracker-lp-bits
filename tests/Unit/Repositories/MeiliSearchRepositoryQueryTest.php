<?php

namespace Tests\Unit\Repositories;

use App\Models\SearchBox;
use App\Repositories\MeiliSearchRepository;
use App\Support\Settings;
use Tests\TestCase;

final class MeiliSearchRepositoryQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $settingsRef = new \ReflectionClass(Settings::class);
        $prop = $settingsRef->getProperty('settings');
        $prop->setAccessible(true);
        $prop->setValue(null, [
            'meilisearch' => ['enabled' => 'no'],
        ]);
    }

    protected function tearDown(): void
    {
        $settingsRef = new \ReflectionClass(Settings::class);
        $prop = $settingsRef->getProperty('settings');
        $prop->setAccessible(true);
        $prop->setValue(null, null);

        parent::tearDown();
    }

    public function test_get_query_handles_array_search_input(): void
    {
        $repo = new MeiliSearchRepository;
        $method = new \ReflectionMethod($repo, 'getQuery');
        $method->setAccessible(true);

        $result = $method->invoke($repo, ['search' => ['x']]);

        $this->assertSame('', $result);
    }

    public function test_get_query_trims_scalar_search_in_and_mode(): void
    {
        $repo = new MeiliSearchRepository;
        $method = new \ReflectionMethod($repo, 'getQuery');
        $method->setAccessible(true);

        $result = $method->invoke($repo, ['search' => '  foo bar  ', 'search_mode' => SearchBox::SEARCH_MODE_AND]);

        $this->assertSame('foo bar', $result);
    }

    public function test_get_query_wraps_scalar_search_in_exact_mode(): void
    {
        $repo = new MeiliSearchRepository;
        $method = new \ReflectionMethod($repo, 'getQuery');
        $method->setAccessible(true);

        $result = $method->invoke($repo, ['search' => '  foo bar  ', 'search_mode' => SearchBox::SEARCH_MODE_EXACT]);

        $this->assertSame('"foo bar"', $result);
    }

    public function test_get_search_area_defaults_on_array_input(): void
    {
        $repo = new MeiliSearchRepository;
        $method = new \ReflectionMethod($repo, 'getSearchArea');
        $method->setAccessible(true);

        $result = $method->invoke($repo, ['search_area' => ['x']]);

        $this->assertSame(MeiliSearchRepository::SEARCH_AREA_TITLE, $result);
    }

    public function test_get_search_area_returns_valid_scalar_value(): void
    {
        $repo = new MeiliSearchRepository;
        $method = new \ReflectionMethod($repo, 'getSearchArea');
        $method->setAccessible(true);

        $result = $method->invoke($repo, ['search_area' => MeiliSearchRepository::SEARCH_AREA_OWNER]);

        $this->assertSame(MeiliSearchRepository::SEARCH_AREA_OWNER, $result);
    }
}
