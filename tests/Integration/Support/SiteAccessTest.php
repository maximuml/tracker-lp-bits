<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Support\Settings;
use App\Support\SiteAccess;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Regression: the guest-visit static page was read from
 * resources/static-pages/<raw setting value> — a path-traversal value
 * stored in settings would be served to every unauthenticated visitor.
 * The value is basename()'d now, so a traversal string resolves to a
 * missing file inside the directory (500) instead of the real target.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SiteAccessTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    private function guestVisitResponse(string $staticPage): int
    {
        DB::table('settings')->updateOrInsert(['name' => 'security.guest_visit_type'], ['value' => 'static_page']);
        DB::table('settings')->updateOrInsert(['name' => 'security.guest_visit_value_static_page'], ['value' => $staticPage]);
        Settings::resetCache();

        try {
            SiteAccess::checkGuestVisit();
        } catch (HttpResponseException $e) {
            return $e->getResponse()->getStatusCode();
        } finally {
            Settings::resetCache();
        }

        return -1;
    }

    public function test_static_page_traversal_value_does_not_serve_outside_files(): void
    {
        $this->assertSame(500, $this->guestVisitResponse('../../composer.json'));
    }

    public function test_static_page_listed_file_is_served(): void
    {
        $this->assertSame(200, $this->guestVisitResponse('beian-aliyun.html'));
    }
}
