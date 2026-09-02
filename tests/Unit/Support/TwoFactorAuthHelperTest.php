<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TwoFactorAuthHelper;
use RobThree\Auth\Providers\Qr\GoogleChartsQrCodeProvider;
use RobThree\Auth\TwoFactorAuth;
use Tests\TestCase;

/**
 * Unit tests for TwoFactorAuthHelper.
 *
 * Tests the TOTP secret generation, code verification, and QR code URL
 * building. These exercise the wrapped RobThree\Auth\TwoFactorAuth
 * library through the helper's static API.
 */
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
            new GoogleChartsQrCodeProvider,
            'Test'
        );
        $code = $tfa->getCode($secret);

        $this->assertTrue(TwoFactorAuthHelper::verifyCode($secret, $code));
    }

    public function test_qr_code_url_returns_google_charts_url(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url = TwoFactorAuthHelper::qrCodeUrl('test@example.com', $secret);

        $this->assertStringStartsWith('https://chart.googleapis.com/chart', $url);
        $this->assertStringContainsString('cht=qr', $url);
        $this->assertStringContainsString('chl=otpauth%3A%2F%2Ftotp%2F', $url);
        $this->assertStringContainsString(urlencode($secret), $url);
    }

    public function test_qr_code_url_includes_label(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $label = 'user@example.com';
        $url = TwoFactorAuthHelper::qrCodeUrl($label, $secret);

        // The label is double-encoded in the URL (once by QRText, once by Google Charts)
        $this->assertStringContainsString('user', $url);
        $this->assertStringContainsString('example.com', $url);
    }

    public function test_qr_code_url_with_custom_size(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url = TwoFactorAuthHelper::qrCodeUrl('test', $secret, 300);

        $this->assertStringContainsString('chs=300x300', $url);
    }

    public function test_qr_code_url_default_size_200(): void
    {
        $secret = TwoFactorAuthHelper::createSecret();
        $url = TwoFactorAuthHelper::qrCodeUrl('test', $secret);

        $this->assertStringContainsString('chs=200x200', $url);
    }
}
