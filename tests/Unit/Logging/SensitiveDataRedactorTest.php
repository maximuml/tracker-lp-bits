<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use App\Logging\SensitiveDataRedactor;
use Illuminate\Log\Logger as IlluminateLogger;
use Monolog\DateTimeImmutable;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Sentinel test: verifies that the SensitiveDataRedactor processor
 * strips credentials, tokens, and other secrets from every part of
 * a Monolog log record before it reaches the handler.
 *
 * Uses unique sentinel values for each sensitive field and asserts
 * that none of them survive redaction in the message, context, or
 * extra sections of the record.
 */
final class SensitiveDataRedactorTest extends TestCase
{
    private SensitiveDataRedactor $redactor;

    protected function setUp(): void
    {
        $this->redactor = SensitiveDataRedactor::instance();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function sensitiveKeysProvider(): array
    {
        return [
            'password' => ['password', 'SENTINEL_PASSWORD_12345'],
            'password_confirmation' => ['password_confirmation', 'SENTINEL_PASS_CONFIRM'],
            'current_password' => ['current_password', 'SENTINEL_CURRENT_PW'],
            'passhash' => ['passhash', 'SENTINEL_PASSHASH_DEADBEEF'],
            'passkey' => ['passkey', 'SENTINEL_PASSKEY_ABCDEF123456'],
            'auth_key' => ['auth_key', 'SENTINEL_AUTH_KEY_ZZZ'],
            'secret' => ['secret', 'SENTINEL_SECRET_VALUE'],
            'editsecret' => ['editsecret', 'SENTINEL_EDIT_SECRET'],
            'token' => ['token', 'SENTINEL_TOKEN_VALUE'],
            'signature' => ['signature', 'SENTINEL_SIG_VALUE'],
            'verity' => ['verity', 'SENTINEL_VERITY_VALUE'],
            'authorization' => ['authorization', 'SENTINEL_BEARER_TOKEN'],
            'cookie' => ['cookie', 'SENTINEL_COOKIE_VALUE'],
            'csrf_token' => ['csrf_token', 'SENTINEL_CSRF_TOKEN'],
            '_token' => ['_token', 'SENTINEL_FORM_TOKEN'],
            'hash' => ['hash', 'SENTINEL_HASH_VALUE'],
            'nonce' => ['nonce', 'SENTINEL_NONCE_VALUE'],
            'api_key' => ['api_key', 'SENTINEL_API_KEY_VALUE'],
            'access_token' => ['access_token', 'SENTINEL_ACCESS_TOKEN'],
            'refresh_token' => ['refresh_token', 'SENTINEL_REFRESH_TOKEN'],
        ];
    }

    /**
     * @param  string  $key  Sensitive key name.
     * @param  string  $value  Unique sentinel value.
     */
    #[DataProvider('sensitiveKeysProvider')]
    public function test_context_array_redacts_sensitive_key(string $key, string $value): void
    {
        $record = $this->makeRecord('test', [$key => $value, 'safe' => 'keep_me']);
        $redacted = $this->redactRecord($record);

        $this->assertSame('[REDACTED]', $redacted->context[$key]);
        $this->assertStringNotContainsString($value, (string) json_encode($redacted->context));
        $this->assertSame('keep_me', $redacted->context['safe']);
    }

    /**
     * @param  string  $key  Sensitive key name.
     * @param  string  $value  Unique sentinel value.
     */
    #[DataProvider('sensitiveKeysProvider')]
    public function test_message_string_redacts_json_pattern(string $key, string $value): void
    {
        $message = sprintf('fail: params: {"%s":"%s","username":"bob"}', $key, $value);
        $record = $this->makeRecord($message, []);
        $redacted = $this->redactRecord($record);

        $this->assertStringNotContainsString($value, $redacted->message);
        $this->assertStringContainsString('[REDACTED]', $redacted->message);
        $this->assertStringContainsString('"username":"bob"', $redacted->message);
    }

    /**
     * @param  string  $key  Sensitive key name.
     * @param  string  $value  Unique sentinel value.
     */
    #[DataProvider('sensitiveKeysProvider')]
    public function test_message_string_redacts_query_string_pattern(string $key, string $value): void
    {
        $message = sprintf('request: %s=%s&username=bob', $key, $value);
        $record = $this->makeRecord($message, []);
        $redacted = $this->redactRecord($record);

        $this->assertStringNotContainsString($value, $redacted->message);
        $this->assertStringContainsString('[REDACTED]', $redacted->message);
        $this->assertStringContainsString('username=bob', $redacted->message);
    }

    /**
     * @param  string  $key  Sensitive key name.
     * @param  string  $value  Unique sentinel value.
     */
    #[DataProvider('sensitiveKeysProvider')]
    public function test_nested_context_redacts_sensitive_key(string $key, string $value): void
    {
        $record = $this->makeRecord('test', [
            'user' => [
                'username' => 'bob',
                $key => $value,
            ],
        ]);
        $redacted = $this->redactRecord($record);

        $this->assertSame('[REDACTED]', $redacted->context['user'][$key]);
        $this->assertSame('bob', $redacted->context['user']['username']);
        $this->assertStringNotContainsString($value, (string) json_encode($redacted->context));
    }

    /**
     * @param  string  $key  Sensitive key name.
     * @param  string  $value  Unique sentinel value.
     */
    #[DataProvider('sensitiveKeysProvider')]
    public function test_extra_section_redacts_sensitive_key(string $key, string $value): void
    {
        $record = $this->makeRecord('test', []);
        $record->extra = [$key => $value, 'request_id' => 'abc-123'];
        $redacted = $this->redactRecord($record);

        $this->assertSame('[REDACTED]', $redacted->extra[$key]);
        $this->assertSame('abc-123', $redacted->extra['request_id']);
        $this->assertStringNotContainsString($value, (string) json_encode($redacted->extra));
    }

    public function test_compound_key_substring_match_redacts(): void
    {
        $record = $this->makeRecord('test', [
            'old_passkey' => 'SENTINEL_OLD_PASSKEY',
            'passhash_algo' => 'SENTINEL_ALGO',
            'user_secret' => 'SENTINEL_USER_SECRET',
        ]);
        $redacted = $this->redactRecord($record);

        $this->assertSame('[REDACTED]', $redacted->context['old_passkey']);
        $this->assertSame('[REDACTED]', $redacted->context['passhash_algo']);
        $this->assertSame('[REDACTED]', $redacted->context['user_secret']);
    }

    public function test_case_insensitive_key_match_redacts(): void
    {
        $record = $this->makeRecord('test', [
            'Password' => 'SENTINEL_MIXED_CASE',
            'TOKEN' => 'SENTINEL_UPPER',
            'PassKey' => 'SENTINEL_PASCAL',
        ]);
        $redacted = $this->redactRecord($record);

        $this->assertSame('[REDACTED]', $redacted->context['Password']);
        $this->assertSame('[REDACTED]', $redacted->context['TOKEN']);
        $this->assertSame('[REDACTED]', $redacted->context['PassKey']);
    }

    public function test_non_sensitive_values_preserved(): void
    {
        $record = $this->makeRecord('user login: username=bob', [
            'username' => 'bob',
            'email' => 'bob@example.com',
            'ip' => '192.168.1.1',
            'action' => 'login',
        ]);
        $redacted = $this->redactRecord($record);

        $this->assertSame('user login: username=bob', $redacted->message);
        $this->assertSame('bob', $redacted->context['username']);
        $this->assertSame('bob@example.com', $redacted->context['email']);
        $this->assertSame('192.168.1.1', $redacted->context['ip']);
        $this->assertSame('login', $redacted->context['action']);
    }

    public function test_tap_callback_pushes_processor_to_monolog(): void
    {
        $monolog = new Logger('test', [new StreamHandler('php://memory')]);
        $illuminateLogger = new IlluminateLogger($monolog);

        $processorsBefore = count($monolog->getProcessors());
        ($this->redactor)($illuminateLogger);
        $processorsAfter = count($monolog->getProcessors());

        $this->assertGreaterThan($processorsBefore, $processorsAfter);
    }

    /**
     * Build a minimal Monolog LogRecord for testing.
     *
     * @param  array<string, mixed>  $context
     */
    private function makeRecord(string $message, array $context): LogRecord
    {
        return new LogRecord(
            new DateTimeImmutable(true),
            'test',
            Level::Info,
            $message,
            $context,
            [],
        );
    }

    /**
     * Run the redactor's processor on a record by extracting it
     * from a real Monolog instance via the public tap callback.
     */
    private function redactRecord(LogRecord $record): LogRecord
    {
        $testMonolog = new Logger('test');
        $illuminateLogger = new IlluminateLogger($testMonolog);
        ($this->redactor)($illuminateLogger);

        $processors = $testMonolog->getProcessors();
        $this->assertNotEmpty($processors);

        return $processors[0]($record);
    }
}
