<?php

declare(strict_types=1);

namespace Tests\Integration\Http\Requests;

use App\Http\Requests\TorrentEditRequest;
use Illuminate\Support\Facades\Validator;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class TorrentEditRequestTest extends TestCase
{
    public function test_sel_spstate_accepts_every_emitted_option(): void
    {
        $rules = (new TorrentEditRequest)->rules();

        foreach (range(1, 7) as $state) {
            $this->assertTrue(
                Validator::make(['sel_spstate' => $state], ['sel_spstate' => $rules['sel_spstate']])->passes(),
                "sel_spstate=$state must validate — the edit form emits it"
            );
        }
    }

    public function test_sel_spstate_rejects_out_of_range_values(): void
    {
        $rules = (new TorrentEditRequest)->rules();

        foreach ([0, 8, -1] as $state) {
            $this->assertFalse(
                Validator::make(['sel_spstate' => $state], ['sel_spstate' => $rules['sel_spstate']])->passes(),
                "sel_spstate=$state must not validate"
            );
        }
    }
}
