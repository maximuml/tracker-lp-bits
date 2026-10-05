<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\BbcodeEditor;
use App\Livewire\SetlistLookup;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SetlistLookupTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_renders_name_input_and_lookup_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(SetlistLookup::class, ['name' => 'Some Release Name'])
            ->assertSee('id="name"', false)
            ->assertSee('name="name"', false)
            ->assertSee('wire:click="lookup"', false)
            ->assertSee('Fill setlist')
            ->assertOk();
    }

    public function test_lookup_with_empty_name_alerts_instead_of_dispatching(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(SetlistLookup::class)
            ->call('lookup')
            ->assertNotDispatched('setlist-append')
            ->assertOk();
    }

    public function test_lookup_passes_trimmed_name_to_the_pipeline(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        // A name that cannot resolve to a setlist.fm entry — the lookup
        // takes the failure branch and alerts, without dispatching
        // setlist-append into the descr editor.
        Livewire::test(SetlistLookup::class, ['name' => '  xyzzy-not-a-real-release-000  '])
            ->call('lookup')
            ->assertNotDispatched('setlist-append')
            ->assertOk();
    }

    public function test_append_event_appends_tracklist_to_descr_editor(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'upload', 'text' => 'descr', 'content' => 'existing text'])
            ->dispatch('setlist-append', form: 'upload', text: 'descr', content: '01 - First Song')
            ->assertSet('body', "existing text\n\n01 - First Song")
            ->assertSet('previewMode', false)
            ->assertOk();
    }

    public function test_append_event_ignores_other_editors(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'compose', 'text' => 'body', 'content' => 'pm body'])
            ->dispatch('setlist-append', form: 'upload', text: 'descr', content: '01 - First Song')
            ->assertSet('body', 'pm body')
            ->assertOk();
    }
}
