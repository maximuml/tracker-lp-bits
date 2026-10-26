<?php

declare(strict_types=1);

namespace Tests\Integration\Support;

use App\Models\User;
use App\Support\UserDisplay;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Byte-level render checks for the user-link component — the
 * `target="_blank"` fragment moved from a trusted-HTML attr param to
 * a bool prop, and must produce identical markup.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UserDisplayRenderTest extends TestCase
{
    use DatabaseTransactions;

    public function test_username_link_emits_target_blank_when_requested(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $html = (string) UserDisplay::username($user->id, false, true, true, true);

        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('href="', $html);
        $this->assertStringContainsString("id={$user->id}", $html);
    }

    public function test_username_link_omits_target_by_default(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $html = (string) UserDisplay::username($user->id, false, true, true, false);

        $this->assertStringNotContainsString('target="_blank"', $html);
        $this->assertStringContainsString('href="', $html);
    }
}
