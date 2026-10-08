<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Offer;

use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Models\Offer;
use App\Services\Offer\OfferAddBuilder;
use App\Services\Offer\OfferEditBuilder;
use App\Support\CurrentUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Kills escaped mutants in the edit/add builders (formerly
 * OfferPageService::buildEditOffer / buildAddOffer): ownership abort,
 * form field assembly, category options, body escaping.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class OfferEditBuilderTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    /** @var OfferRepositoryInterface&MockInterface */
    private OfferRepositoryInterface $offerRepo;

    private int $initialObLevel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialObLevel = ob_get_level();
        Redis::connection()->flushdb();

        /** @var OfferRepositoryInterface&MockInterface $offerRepo */
        $offerRepo = Mockery::mock(OfferRepositoryInterface::class);
        $this->offerRepo = $offerRepo;

        $currentUser = new CurrentUser;
        $currentUser->set(['id' => 7, 'username' => 'u', 'class' => 0]);
        $this->app->instance(CurrentUser::class, $currentUser);

        DB::table('categories')->insert([
            'mode' => 1, 'class_name' => 'test_cat', 'name' => 'ZZ Category', 'image' => 'x.gif', 'sort_index' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        while (ob_get_level() > $this->initialObLevel) {
            ob_end_clean();
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @param  array<string, mixed>  $attributes */
    private function offer(array $attributes = []): Offer
    {
        return (new Offer)->setRawAttributes(array_merge([
            'id' => 5,
            'userid' => 10,
            'name' => ' <b>Offer</b> ',
            'descr' => 'descr & <i>stuff</i>',
            'category' => 3,
        ], $attributes));
    }

    private function request(int $id = 5): Request
    {
        return Request::create('/web/offers?id='.$id, 'GET');
    }

    private function assertAbortContains(callable $fn, string $needle): void
    {
        set_error_handler(static fn (int $severity): bool => true, E_NOTICE | E_WARNING | E_USER_NOTICE | E_USER_WARNING);
        try {
            $fn();
            $this->fail('Expected abort');
        } catch (HttpResponseException $e) {
            $this->assertStringContainsString(e($needle), (string) $e->getResponse()->getContent());
        } finally {
            restore_error_handler();
        }
    }

    public function test_missing_offer_aborts(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->with(5)->andReturn(null);

        $this->assertAbortContains(
            fn () => $this->editBuilder()->build($this->curUser(['id' => 7]), 7, $this->request(), 1),
            (string) __('offers.text_nothing_found'),
        );
    }

    public function test_non_owner_low_class_aborts(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['userid' => 10]));

        $this->assertAbortContains(
            fn () => $this->editBuilder()->build($this->curUser(['id' => 7]), 7, $this->request(), 1),
            (string) __('offers.std_cannot_edit_others_offer'),
        );
    }

    public function test_owner_gets_form_data(): void
    {
        $this->offerRepo->shouldReceive('findOffer')->andReturn($this->offer(['userid' => 7]));

        $r = $this->editBuilder()->build($this->curUser(['id' => 7]), 7, $this->request(), 1);

        $this->assertSame(5, $r['id']);
        $this->assertSame('&lt;b&gt;Offer&lt;/b&gt;', $r['title']);
        $this->assertSame(3, $r['catId']);
        $this->assertSame('descr &amp; &lt;i&gt;stuff&lt;/i&gt;', $r['bodyContent']);
        $this->assertNotEmpty($r['catOptions']);
        $this->assertContains('ZZ Category', array_map(static fn ($o) => $o->name, $r['catOptions']));
    }

    public function test_add_builder_returns_options_and_empty_body(): void
    {
        $r = (new OfferAddBuilder)->build(1);

        $this->assertSame('', $r['bodyContent']);
        $this->assertNotEmpty($r['typeOptions']);
        $this->assertContains('ZZ Category', array_map(static fn ($o) => $o->name, $r['typeOptions']));
    }

    private function editBuilder(): OfferEditBuilder
    {
        return new OfferEditBuilder($this->offerRepo);
    }
}
