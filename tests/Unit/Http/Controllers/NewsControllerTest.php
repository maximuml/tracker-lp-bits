<?php

namespace Tests\Unit\Http\Controllers;

use App\Http\Controllers\NewsController;
use App\Http\Requests\NewsUpdateRequest;
use App\Models\News;
use App\Support\Cache\LegacyRedisCache;
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

final class NewsControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Bind a mock LegacyRedisCache so controller cache-clear calls do not hit Redis.
     */
    private function mockCache(): void
    {
        $cache = Mockery::mock(LegacyRedisCache::class);
        $cache->shouldReceive('delete_value')->andReturn(1);
        app()->instance(LegacyRedisCache::class, $cache);
    }

    public function test_destroy_deletes_news_and_returns_success(): void
    {
        $this->mockCache();

        /** @var News&Mockery\MockInterface $news */
        $news = Mockery::mock(News::class);
        $news->shouldReceive('delete')->once()->andReturn(true);

        $controller = app(NewsController::class);
        $request = Request::create('/api/news/1', 'DELETE');
        app()->instance('request', $request);

        $result = $controller->destroy($news);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('News deleted', $result['msg']);
        $this->assertTrue($result['data']['success']);
    }

    public function test_update_modifies_news_and_returns_resource(): void
    {
        $this->mockCache();

        /** @var News&Mockery\MockInterface $news */
        $news = Mockery::mock(News::class)->makePartial();
        $news->shouldReceive('update')->once()->andReturn(true);

        $freshNews = new News;
        $freshNews->setRawAttributes([
            'id' => 1,
            'title' => 'Updated Title',
            'body' => 'Updated body content',
            'added' => '2024-01-01 00:00:00',
            'userid' => 1,
            'notify' => 'no',
        ], true);
        $news->shouldReceive('fresh')->once()->andReturn($freshNews);

        $controller = app(NewsController::class);
        $request = NewsUpdateRequest::create('/api/news/1', 'PUT', [
            'title' => 'Updated Title',
            'body' => 'Updated body content',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->update($request, $news);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('News updated', $result['msg']);
        $this->assertIsArray($result['data']);
        $this->assertArrayHasKey('data', $result['data']);
        $this->assertSame(1, $result['data']['data']['id']);
        $this->assertSame('Updated Title', $result['data']['data']['title']);
    }

    public function test_update_validates_notify_field(): void
    {
        $this->mockCache();

        /** @var News&Mockery\MockInterface $news */
        $news = Mockery::mock(News::class)->makePartial();
        $news->shouldReceive('update')->once()->andReturn(true);

        $freshNews = new News;
        $freshNews->setRawAttributes([
            'id' => 2,
            'title' => 'Notify Test',
            'body' => 'Body text',
            'added' => '2024-06-01 12:00:00',
            'userid' => 1,
            'notify' => 'yes',
        ], true);
        $news->shouldReceive('fresh')->once()->andReturn($freshNews);

        $controller = app(NewsController::class);
        $request = NewsUpdateRequest::create('/api/news/2', 'PUT', [
            'notify' => 'yes',
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->validateResolved();
        app()->instance('request', $request);

        $result = $controller->update($request, $news);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('News updated', $result['msg']);
        $this->assertSame(2, $result['data']['data']['id']);
    }

    public function test_show_returns_single_news_resource(): void
    {
        /** @var News&Mockery\MockInterface $news */
        $news = Mockery::mock(News::class)->makePartial();
        $news->setRawAttributes([
            'id' => 7,
            'title' => 'Breaking News',
            'body' => 'Something happened today',
            'added' => '2024-03-15 10:30:00',
            'userid' => 1,
            'notify' => 'no',
        ], true);
        $news->shouldReceive('load')->once()->with(['user'])->andReturnSelf();

        $controller = app(NewsController::class);
        $request = Request::create('/api/news/7', 'GET');
        app()->instance('request', $request);

        $result = $controller->show($news);

        $this->assertSame(0, $result['ret']);
        $this->assertSame('news.detail', $result['msg']);
        $this->assertIsArray($result['data']);
        $this->assertSame(7, $result['data']['data']['id']);
        $this->assertSame('Breaking News', $result['data']['data']['title']);
    }
}
