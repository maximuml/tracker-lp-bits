<?php

namespace Tests\Unit\Support;

use App\Support\Validators;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

#[TestCategory(TestCategory::PURE_UNIT)]
class ValidatorsTest extends TestCase
{
    // ---------- isId() ----------

    public function test_is_id_accepts_positive_integers(): void
    {
        $this->assertTrue(Validators::isId(1));
        $this->assertTrue(Validators::isId(42));
        $this->assertTrue(Validators::isId(99999));
    }

    public function test_is_id_accepts_numeric_strings(): void
    {
        $this->assertTrue(Validators::isId('1'));
        $this->assertTrue(Validators::isId('42'));
        // Decimal-looking-but-integer-valued strings pass too —
        // `floor('1.0') == '1.0'` is true.
        $this->assertTrue(Validators::isId('1.0'));
    }

    public function test_is_id_rejects_zero_negative_and_non_integral(): void
    {
        $this->assertFalse(Validators::isId(0));
        $this->assertFalse(Validators::isId(-1));
        $this->assertFalse(Validators::isId(1.5));
        $this->assertFalse(Validators::isId('1.5'));
    }

    public function test_is_id_rejects_non_numeric_and_null(): void
    {
        $this->assertFalse(Validators::isId('abc'));
        $this->assertFalse(Validators::isId(''));
        $this->assertFalse(Validators::isId(null));
        $this->assertFalse(Validators::isId(false));
        $this->assertFalse(Validators::isId([]));
    }

    // ---------- isUserClass() ----------

    public function test_is_user_class_accepts_known_tiers(): void
    {
        $this->assertTrue(Validators::isUserClass(0));
        $this->assertTrue(Validators::isUserClass(1));
        $this->assertTrue(Validators::isUserClass(8));
        $this->assertTrue(Validators::isUserClass(14));
        $this->assertTrue(Validators::isUserClass(15));
        $this->assertTrue(Validators::isUserClass(16));
    }

    public function test_is_user_class_accepts_numeric_strings(): void
    {
        $this->assertTrue(Validators::isUserClass('0'));
        $this->assertTrue(Validators::isUserClass('1'));
        $this->assertTrue(Validators::isUserClass('16'));
    }

    public function test_is_user_class_rejects_out_of_range(): void
    {
        $this->assertFalse(Validators::isUserClass(-1));
        $this->assertFalse(Validators::isUserClass(17));
        $this->assertFalse(Validators::isUserClass(99));
    }

    public function test_is_user_class_rejects_fractional_and_non_numeric(): void
    {
        $this->assertFalse(Validators::isUserClass(1.5));
        $this->assertFalse(Validators::isUserClass('1.5'));
        $this->assertFalse(Validators::isUserClass('abc'));
        $this->assertFalse(Validators::isUserClass(''));
        $this->assertFalse(Validators::isUserClass(null));
    }

    public function test_is_user_class_legacy_constants_match_core_php(): void
    {
        $this->assertSame(0, Validators::USER_CLASS_MIN);
        $this->assertSame(16, Validators::USER_CLASS_MAX);
    }
}
