<?php

declare(strict_types=1);

namespace Tests\Integration\Livewire;

use App\Livewire\BbcodeEditor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Redis;
use Livewire\Livewire;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class BbcodeEditorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
    }

    public function test_render_edit_mode_shows_editor_and_preview_button(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'compose', 'text' => 'body', 'content' => 'hello [b]world[/b]'])
            ->assertSee('bbcode-editor')
            ->assertSee('Preview')
            ->assertSee('wire:model="body"', false)
            ->assertOk();
    }

    public function test_preview_renders_formatted_bbcode(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'compose', 'text' => 'body', 'content' => 'hello [b]world[/b]'])
            ->call('preview')
            ->assertSet('previewMode', true)
            ->assertSee('nx-box')
            ->assertSee('Edit')
            ->assertOk();
    }

    public function test_unpreview_returns_to_edit_mode(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'compose', 'text' => 'body', 'content' => 'x'])
            ->call('preview')
            ->call('unpreview')
            ->assertSet('previewMode', false)
            ->assertSee('Preview')
            ->assertOk();
    }

    public function test_textarea_keeps_form_field_name(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'nexus-web');

        Livewire::test(BbcodeEditor::class, ['form' => 'compose', 'text' => 'body'])
            ->assertSee('name="body"', false)
            ->assertOk();
    }
}
