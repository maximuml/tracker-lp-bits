<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\OfferController;
use Illuminate\Http\Request;
use Tests\TestCase;

final class OfferControllerTest extends TestCase
{
    public function test_index_returns_paginated_offer_list(): void
    {
        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET', ['page' => 1, 'per_page' => 5]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('offer.list', $result['msg']);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('data', $result['data']);
        $this->assertArrayHasKey('total', $result['data']);
        $this->assertArrayHasKey('page', $result['data']);
        $this->assertArrayHasKey('per_page', $result['data']);
        $this->assertArrayHasKey('filters', $result['data']);
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET', ['per_page' => 10]);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(10, $result['data']['per_page']);
    }

    public function test_index_defaults_per_page_to_25(): void
    {
        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET');
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame(25, $result['data']['per_page']);
    }

    public function test_index_passes_search_filter(): void
    {
        $controller = app(OfferController::class);
        $request = Request::create('/api/offers', 'GET', ['search' => 'ubuntu']);
        app()->instance('request', $request);

        $result = $controller->index($request);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('ubuntu', $result['data']['filters']['search']);
    }
}
