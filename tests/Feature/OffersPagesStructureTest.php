<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Structure contract for /web/offers after the card redesign: visible
 * heading, collapsible rules, search card, votes column with progress,
 * status chips — while the GET/POST field contract stays identical.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class OffersPagesStructureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_list_has_heading_rules_details_and_search_card(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->withNexusCookie($user)->get('/web/offers');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            'nx-off__top',
            'nx-off__h1',
            'nx-off__sub',
            'nx-off__rules',
            '<summary>',
            'nx-off__search',
            'name="search"',
            'name="category"',
            '?add_offer=1',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_list_table_keeps_vote_and_manage_contracts(): void
    {
        $user = User::factory()->admin()->create();
        $offer = new Offer;
        $offer->fill([
            'userid' => $user->id,
            'name' => 'Structure Test Offer',
            'descr' => 'A description.',
            'added' => now(),
        ]);
        $offer->yeah = 15;
        $offer->against = 8;
        $offer->category = (int) Category::query()->value('id');
        $offer->save();

        $response = $this->withNexusCookie($user)->get('/web/offers');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            'nx-offtable',
            'nx-off__chip',
            'nx-off__bar',
            'nx-off__meta',
            'Structure Test Offer',
            'action="/web/offers/vote"',
            'name="vote"',
            'off_details=1',
            'more to approve',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }

    public function test_add_offer_form_keeps_field_and_endpoint_contract(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->withNexusCookie($user)->get('/web/offers?add_offer=1');

        $response->assertOk();
        $html = (string) $response->getContent();

        foreach ([
            'action="/web/offers/create"',
            'name="type"',
            'name="name"',
            'name="picture"',
            'id="qr"',
            'id="compose"',
            'nx-setcards',
            'nx-savebar',
        ] as $marker) {
            $this->assertStringContainsString($marker, $html);
        }
    }
}
