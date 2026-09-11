<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LocalQrCodeProvider;
use App\Support\TwoFactorAuthHelper;
use RobThree\Auth\TwoFactorAuth;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for TwoFactorAuthHelper.
 *
 * Tests the TOTP secret generation, code verification, and QR code URL
 * building. These exercise the wrapped RobThree\Auth\TwoFactorAuth
 * library through the helper's static API.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class TwoFactorAuthHelperTest extends TestCase
{
    public function test_create_secret_returns_base32_string(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();

        // Default 80 bits → 16 base32 chars
        $this->assertSame(16, strlen($secret));
        // Base32 alphabet: A-Z, 2-7
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_create_secret_with_custom_bits(): void
    {
        // 160 bits → 32 base32 chars
        $secret = TwoFactorAuthHelper::createSecret(160);

        $this->assertSame(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_create_secret_generates_unique_values(): void
    {
        $secret1 = TwoFactorAuthHelper::createSecret();
        $secret2 = TwoFactorAuthHelper::createSecret();

        $this->assertNotSame($secret1, $secret2);
    }

    public function test_verify_code_rejects_empty_code(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();

        $this->assertFalse(TwoFactorAuthHelper::verifyCode($secret, ''));
    }

    public function test_verify_code_rejects_wrong_code(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();

        // A completely wrong code should not verify
        $this->assertFalse(TwoFactorAuthHelper::verifyCode($secret, '000000'));
    }

    public function test_verify_code_accepts_correct_code(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();

        // Generate a valid TOTP code using the underlying library
        $tfa = new TwoFactorAuth(
            new LocalQrCodeProvider,
            'Test'
        );
        $code = $tfa->getCode($secret);

        $this->assertTrue(TwoFactorAuthHelper::verifyCode($secret, $code));
    }

    public function test_qr_code_url_returns_local_png_data_uri(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url = TwoFactorAuthHelper::qrCodeUrl('test@example.com', $secret);

        $this->assertStringStartsWith('data:image/png;base64,', $url);
        $png = base64_decode(substr($url, 22), true);
        $this->assertNotFalse($png);
        $this->assertStringStartsWith("\x89PNG", $png);
    }

    public function test_qr_code_url_includes_label(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url1 = TwoFactorAuthHelper::qrCodeUrl('user@example.com', $secret);
        $url2 = TwoFactorAuthHelper::qrCodeUrl('other@example.com', $secret);

        $this->assertNotSame($url1, $url2);
    }

    public function test_qr_code_url_with_custom_size(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $small = TwoFactorAuthHelper::qrCodeUrl('test', $secret, 100);
        $large = TwoFactorAuthHelper::qrCodeUrl('test', $secret, 300);

        $pngS = getimagesizefromstring(base64_decode(substr($small, 22), true));
        $pngL = getimagesizefromstring(base64_decode(substr($large, 22), true));
        $this->assertNotFalse($pngS);
        $this->assertNotFalse($pngL);
        $this->assertGreaterThan($pngS[0], $pngL[0]);
    }

    public function test_qr_code_url_default_size_200(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url = TwoFactorAuthHelper::qrCodeUrl('test', $secret);

        $png = getimagesizefromstring(base64_decode(substr($url, 22), true));
        $this->assertNotFalse($png);
        $this->assertSame($png[0], $png[1]);
        $this->assertGreaterThanOrEqual(100, $png[0]);
    }
}
