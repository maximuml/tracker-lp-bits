<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\SecureTokenService;
use PHPUnit\Framework\TestCase;
use Tests\Attributes\TestCategory;

/**
 * Pure-crypto coverage for the email-change token format added in step 3.1.
 * DB-backed methods (store/consume/verify) are covered in
 * tests/Integration/Services/SecureTokenServiceTest.php.
 */
#[TestCategory(TestCategory::PURE_UNIT)]
final class SecureTokenServiceTest extends TestCase
{
    private SecureTokenService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SecureTokenService;
    }

    public function test_email_change_digest_is_64_char_sha256(): void
    {
        $digest = $this->service->emailChangeDigest('token123', 'a@example.com');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $digest);
    }

    public function test_email_change_digest_binds_email(): void
    {
        $token = $this->service->generate();

        $this->assertNotSame(
            $this->service->emailChangeDigest($token, 'a@example.com'),
            $this->service->emailChangeDigest($token, 'b@example.com'),
        );
    }

    public function test_verify_email_change_accepts_new_format(): void
    {
        $token = $this->service->generate();
        $stored = $this->service->emailChangeDigest($token, 'new@example.com');

        $this->assertTrue($this->service->verifyEmailChangeToken($stored, 'new@example.com', $token));
    }

    public function test_verify_email_change_rejects_tampered_email(): void
    {
        $token = $this->service->generate();
        $stored = $this->service->emailChangeDigest($token, 'new@example.com');

        $this->assertFalse($this->service->verifyEmailChangeToken($stored, 'other@example.com', $token));
    }

    public function test_verify_email_change_rejects_empty_stored_secret(): void
    {
        $token = $this->service->generate();

        $this->assertFalse($this->service->verifyEmailChangeToken('', 'new@example.com', $token));
    }

    public function test_verify_email_change_accepts_legacy_md5(): void
    {
        $storedSecret = 'deadbeefcafe1234567890abcdef1234567890ab';
        $sec = str_pad($storedSecret, 20);
        $email = 'new@example.com';
        $token = md5($sec.$email.$sec);

        $this->assertTrue($this->service->verifyEmailChangeToken($storedSecret, $email, $token));
    }

    public function test_verify_email_change_rejects_legacy_md5_wrong_email(): void
    {
        $storedSecret = 'deadbeefcafe1234567890abcdef1234567890ab';
        $sec = str_pad($storedSecret, 20);
        $token = md5($sec.'new@example.com'.$sec);

        $this->assertFalse($this->service->verifyEmailChangeToken($storedSecret, 'other@example.com', $token));
    }

    public function test_verify_email_change_rejects_blank_legacy_secret(): void
    {
        $this->assertFalse($this->service->verifyEmailChangeToken('   ', 'a@example.com', str_repeat('a', 32)));
    }

    public function test_verify_email_change_rejects_wrong_length(): void
    {
        $this->assertFalse($this->service->verifyEmailChangeToken('whatever', 'a@example.com', 'short'));
    }
}
