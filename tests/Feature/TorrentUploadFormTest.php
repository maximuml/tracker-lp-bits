<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserClass;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Rhilip\Bencode\Bencode;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * UX-01: upload errors must return the form with old input and visible
 * field errors instead of redirecting to /error and discarding the entry.
 */
#[TestCategory(TestCategory::HTTP_FEATURE)]
final class TorrentUploadFormTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'null', 'app.debug' => false]);
    }

    public function test_missing_description_returns_form_with_old_input_not_error_page(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Keep My Title 2026',
                'cnname' => 'Chinese Title',
                'technical_info' => 'General\nFormat: Matroska',
                'type' => (string) $category->id,
                'offer' => '0',
                'uplver' => 'yes',
                // descr missing
            ]);

        $response->assertRedirect('/upload');
        $response->assertSessionHasErrors('descr');
        $response->assertSessionHasInput([
            'name' => 'Keep My Title 2026',
            'cnname' => 'Chinese Title',
            'technical_info' => 'General\nFormat: Matroska',
            'type' => (string) $category->id,
            'uplver' => 'yes',
        ]);
    }

    public function test_form_rerender_shows_old_values_and_visible_errors(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Restored Title',
                'type' => (string) $category->id,
            ])
            ->assertRedirect('/upload');

        $page = $this->withNexusCookie($user)->get('/web/upload');

        $page->assertOk();
        $page->assertSee('value="Restored Title"', false);
        $page->assertSee('role="alert"', false);
        $page->assertSee('aria-invalid="true"', false);
        $page->assertSee('aria-describedby="descr-error"', false);
        $page->assertSee('id="descr-error"', false);
        $page->assertSee('selected', false);
        $page->assertSee('bg-nxm-danger-bg', false);
        $page->assertSee(__('legacy/upload.reselect_file_note'), false);
        $page->assertDontSee('/error?error=', false);
    }

    public function test_unbencoded_file_error_keeps_description_and_targets_file_field(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Bad Torrent Upload',
                'descr' => 'A description that must survive the failed upload.',
                'type' => (string) $category->id,
                'file' => UploadedFile::fake()->createWithContent('not-a-torrent.torrent', 'definitely not bencoded'),
            ]);

        $response->assertRedirect('/upload');
        $response->assertSessionHasErrors('file');
        $response->assertSessionHasInput('descr', 'A description that must survive the failed upload.');
    }

    public function test_unknown_category_error_targets_type_field(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Bad Category Upload',
                'descr' => 'Description stays.',
                'type' => '999999999',
                'file' => $this->torrentUpload(),
            ]);

        $response->assertRedirect('/upload');
        $response->assertSessionHasErrors('type');
    }

    public function test_anonymous_upload_without_permission_targets_uplver_field(): void
    {
        // POWER_USER can upload (min class 2) but cannot hide their name
        // (beanonymous requires class 4) — deterministic on any weekday.
        $user = User::factory()->class(UserClass::POWER_USER->value)->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Anonymous Attempt',
                'descr' => 'Description to keep.',
                'type' => (string) $category->id,
                'uplver' => 'yes',
                'file' => $this->torrentUpload(),
            ]);

        $response->assertRedirect('/upload');
        $response->assertSessionHasErrors('uplver');
        $response->assertSessionHasInput('descr', 'Description to keep.');
    }

    public function test_missing_torrent_file_reports_file_error_and_keeps_name(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Title Without File',
                'descr' => 'Has a description.',
                'type' => (string) $category->id,
            ]);

        $response->assertRedirect('/upload');
        $response->assertSessionHasErrors('file');
        $response->assertSessionHasInput('name', 'Title Without File');
    }

    public function test_name_falls_back_to_torrent_metadata_name(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'descr' => 'Description for unnamed upload.',
                'type' => (string) $category->id,
                'file' => $this->torrentUpload('Meta Name Release 2026'),
            ]);

        $response->assertRedirect();

        $torrent = Torrent::query()
            ->where('owner', $user->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($torrent);
        $this->assertSame('Meta Name Release 2026', $torrent->name);
        $response->assertRedirect('/web/details/'.$torrent->id.'?uploaded=1');
    }

    public function test_rerender_restores_every_control_kind(): void
    {
        // Admin has every permission, so price/position/anonymous/offer
        // controls render and their old values must come back.
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();
        $mode = (int) $category->mode;

        $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'name' => 'Full Restore Title',
                'type' => (string) $category->id,
                "source_sel[{$mode}]" => '1',
                "tags[{$mode}]" => ['5', '9'],
                "hr[{$mode}]" => '0',
                "custom_fields[{$mode}]" => ['7' => 'custom value'],
                'offer' => '3',
                'price' => '15',
                'pos_state' => '1',
                'pos_state_until' => '2030-01-01T00:00',
                'uplver' => 'yes',
                // descr missing -> redirect back with everything flashed
            ])
            ->assertRedirect('/upload');

        $page = $this->withNexusCookie($user)->get('/web/upload');

        $page->assertOk();
        $page->assertSee('value="Full Restore Title"', false);
        $page->assertSee('value="15"', false);
        $page->assertSee('value="2030-01-01T00:00"', false);
        $page->assertSee('checked', false);
        $page->assertSee('bg-nxm-danger-bg', false);
    }

    public function test_upload_form_includes_csrf_token(): void
    {
        $user = User::factory()->admin()->create();

        $page = $this->withNexusCookie($user)->get('/web/upload');

        $page->assertOk();
        $page->assertSee('name="_token"', false);
    }

    public function test_upload_errors_do_not_leak_into_error_query_redirect(): void
    {
        $user = User::factory()->admin()->create();
        $category = $this->browseCategory();

        $response = $this->withNexusCookie($user)
            ->withSession(['_token' => 'ux01-token'])
            ->from('/upload')
            ->post('/takeupload', [
                '_token' => 'ux01-token',
                'type' => (string) $category->id,
                'file' => UploadedFile::fake()->createWithContent('v2.torrent', 'bad'),
            ]);

        $response->assertRedirect('/upload');
        $this->assertStringNotContainsString('/error', (string) $response->headers->get('Location'));
    }

    /**
     * A category inside the browse section — UploadMetadataService rejects
     * categories whose mode is not the configured browse SearchBox id.
     */
    private function browseCategory(): Category
    {
        $mode = SearchBox::getBrowseMode();
        $category = Category::query()->where('mode', $mode)->first();
        if (! $category instanceof Category) {
            $category = Category::factory()->create(['mode' => $mode]);
        }

        return $category;
    }

    private function torrentUpload(string $name = 'UX01 Test Release'): UploadedFile
    {
        $data = [
            'announce' => 'http://openresty/announce.php',
            'comment' => 'ux01 test',
            'created by' => 'PHPUnit',
            'creation date' => time(),
            'encoding' => 'UTF-8',
            'info' => [
                'length' => 1234,
                'name' => $name,
                'piece length' => 1048576,
                'pieces' => str_repeat('A', 20),
            ],
        ];

        $path = tempnam(sys_get_temp_dir(), 'ux01_');
        file_put_contents($path, Bencode::encode($data));

        return new UploadedFile($path, 'release.torrent', 'application/x-bittorrent', null, true);
    }
}
