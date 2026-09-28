<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BackupTransferService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

#[TestCategory(TestCategory::PURE_UNIT)]
final class BackupTransferServiceTest extends TestCase
{
    private BackupTransferService $service;

    private string $credentialsFile = '';

    private string $archiveFile = '';

    private string $privateKey = '';

    private string $publicKey = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BackupTransferService;
        $this->archiveFile = tempnam(sys_get_temp_dir(), 'backup_').'.tar.gz';
        file_put_contents($this->archiveFile, 'fake-archive-content');

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        $this->assertTrue(openssl_pkey_export($key, $privateKeyPem));
        $this->privateKey = $privateKeyPem;
        $details = openssl_pkey_get_details($key);
        $this->assertNotFalse($details);
        $this->publicKey = $details['key'];

        $this->credentialsFile = tempnam(sys_get_temp_dir(), 'gdrive_').'.json';
        file_put_contents($this->credentialsFile, json_encode([
            'client_email' => 'backup@project.iam.gserviceaccount.com',
            'private_key' => $this->privateKey,
            'token_uri' => 'https://oauth2.example.test/token',
        ]));
        config(['backup.gdrive_credentials' => $this->credentialsFile]);
        config(['backup.gdrive_folder_id' => '']);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentialsFile);
        @unlink($this->archiveFile);
        parent::tearDown();
    }

    public function test_gdrive_skipped_when_flag_off(): void
    {
        Http::fake();
        $result = $this->service->saveToGdrive(['via_gdrive' => 'no'], $this->archiveFile);
        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    public function test_gdrive_skipped_when_credentials_missing(): void
    {
        config(['backup.gdrive_credentials' => '/nonexistent/creds.json']);
        $result = $this->service->saveToGdrive(['via_gdrive' => 'yes'], $this->archiveFile);
        $this->assertFalse($result);
    }

    public function test_gdrive_uploads_with_signed_jwt_bearer_grant(): void
    {
        Http::fake([
            'oauth2.example.test/*' => Http::response(['access_token' => 'ya29.test-token'], 200),
            'googleapis.com/*' => Http::response(['id' => 'drive-file-id'], 200),
        ]);

        $result = $this->service->saveToGdrive(['via_gdrive' => 'yes'], $this->archiveFile);

        $this->assertTrue($result);
        Http::assertSentCount(2);

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'oauth2.example.test/token')) {
                return true;
            }
            $body = [];
            parse_str($request->body(), $body);
            $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $body['grant_type'] ?? '');
            $assertion = $body['assertion'] ?? '';
            $this->assertIsString($assertion);
            $parts = explode('.', $assertion);
            $this->assertCount(3, $parts);
            $claims = json_decode($this->base64UrlDecode($parts[1]), true);
            $this->assertIsArray($claims);
            $this->assertSame('backup@project.iam.gserviceaccount.com', $claims['iss']);
            $this->assertSame('https://www.googleapis.com/auth/drive.file', $claims['scope']);
            $signature = $this->base64UrlDecode($parts[2]);
            $this->assertSame(1, openssl_verify("{$parts[0]}.{$parts[1]}", $signature, $this->publicKey, OPENSSL_ALGO_SHA256));

            return true;
        });

        Http::assertSent(function (Request $request): bool {
            if (! str_contains($request->url(), 'upload/drive/v3/files')) {
                return true;
            }
            $this->assertStringContainsString('uploadType=multipart', $request->url());
            $this->assertTrue($request->hasHeader('Authorization', 'Bearer ya29.test-token'));

            return true;
        });
    }

    public function test_gdrive_returns_error_string_on_upload_failure(): void
    {
        Http::fake([
            'oauth2.example.test/*' => Http::response(['access_token' => 'ya29.test-token'], 200),
            'googleapis.com/*' => Http::response(['error' => 'forbidden'], 403),
        ]);

        $result = $this->service->saveToGdrive(['via_gdrive' => 'yes'], $this->archiveFile);

        $this->assertIsString($result);
        $this->assertStringContainsString('Drive upload failed', $result);
    }

    public function test_gdrive_returns_error_string_on_token_failure(): void
    {
        Http::fake([
            'oauth2.example.test/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $result = $this->service->saveToGdrive(['via_gdrive' => 'yes'], $this->archiveFile);

        $this->assertIsString($result);
        $this->assertStringContainsString('token exchange failed', $result);
    }

    private function base64UrlDecode(string $data): string
    {
        return (string) base64_decode(strtr($data, '-_', '+/'));
    }
}
