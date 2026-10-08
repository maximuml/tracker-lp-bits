<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::HTTP_FEATURE)]
class LegacyShimCounterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->del('metrics:legacy_shim_keys');
        foreach (['rules:301', 'details/{id}:301', 'takemessage:308', 'comment/add:301', 'comment/add:302'] as $key) {
            Redis::connection()->del("metrics:legacy_shim:{$key}");
        }
    }

    protected function tearDown(): void
    {
        Redis::connection()->del('metrics:legacy_shim_keys');
        parent::tearDown();
    }

    private function sysop(): User
    {
        /** @var User $user */
        $user = User::factory()->create(['class' => UserClass::SYSOP->value]);

        return $user;
    }

    public function test_get_301_shim_increments_counter(): void
    {
        $this->get('/rules')->assertRedirect('/web/rules');

        $this->assertSame('1', Redis::connection()->get('metrics:legacy_shim:rules:301'));
        $this->assertContains('rules:301', (array) Redis::connection()->smembers('metrics:legacy_shim_keys'));
    }

    public function test_uri_label_collapses_route_parameters(): void
    {
        $sysop = $this->sysop();

        $this->withNexusCookie($sysop)->get('/details/123')
            ->assertRedirect();

        $this->assertSame('1', Redis::connection()->get('metrics:legacy_shim:details/{id}:301'));
    }

    public function test_post_308_dispatcher_increments_counter(): void
    {
        $sysop = $this->sysop();
        $receiver = User::factory()->create();

        $this->withNexusCookie($sysop)->post('/takemessage', [
            'receiver' => $receiver->id,
            'subject' => 'hi',
            'body' => 'body',
        ])->assertRedirect('/web/messages/send');

        $this->assertSame('1', Redis::connection()->get('metrics:legacy_shim:takemessage:308'));
    }

    public function test_non_redirect_response_is_not_counted(): void
    {
        $sysop = $this->sysop();

        // Canonical endpoint living in the legacy route file — returns a
        // view, so the shim counter must stay silent.
        $this->withNexusCookie($sysop)->get('/comment/add?type=torrent&id=1');

        $keys = (array) Redis::connection()->smembers('metrics:legacy_shim_keys');
        $this->assertNotContains('comment/add:301', $keys);
        $this->assertNotContains('comment/add:302', $keys);
    }
}
