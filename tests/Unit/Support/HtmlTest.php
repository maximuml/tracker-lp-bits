<?php

namespace Tests\Unit\Support;

use App\Support\Html;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
final class HtmlTest extends TestCase
{
    // ---------- tableRow ----------

    // ---------- keyShortcutScript ----------

    // ---------- promotionSelectOptions ----------

    /**
     * @return array<string, string>
     */

    // ---------- torrentSelect ----------

    // ---------- settingsRow ----------

    // ---------- settingsRowSmall ----------

    // ---------- settingsCells ----------

    // ---------- tooltipContainer ----------

    public function test_tooltip_container_with_empty_input_returns_empty(): void
    {
        $this->assertSame('', Html::tooltipContainer([]));
    }

    // ---------- messageAlert ----------

    // ---------- buildTable() ----------

}
