<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Contracts\Repositories\ToolRepositoryInterface;
use App\Jobs\SendLegacyMail;
use App\Support\Mail;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use ReflectionClass;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * @covers \App\Jobs\SendLegacyMail
 * @covers \App\Support\Mail::queueLegacy
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class SendLegacyMailTest extends TestCase
{
    use DatabaseTransactions;

    private function resetSettingsCache(): void
    {
        $reflection = new ReflectionClass(Settings::class);
        $reflection->getProperty('settings')->setValue(null, null);
        $reflection->getProperty('fromDb')->setValue(null, null);
    }

    private function setSmtpType(string $type): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'smtp.smtptype'],
            ['value' => $type],
        );
        $this->resetSettingsCache();
    }

    private function job(): SendLegacyMail
    {
        return new SendLegacyMail(
            'user@test.com',
            'Site',
            'site@test.com',
            'Subject',
            'Body',
            'test',
            false,
            '',
            'UTF-8',
        );
    }

    public function test_queue_legacy_dispatches_job_to_mail_queue(): void
    {
        Queue::fake();

        $queued = Mail::queueLegacy(
            'user@test.com',
            'Site',
            'site@test.com',
            'Subject',
            'Body',
            'sendmessage',
            false,
            false,
            '',
            'UTF-8',
        );

        $this->assertTrue($queued);
        Queue::assertPushedOn('mail', SendLegacyMail::class);
        Queue::assertPushed(SendLegacyMail::class, function (SendLegacyMail $job): bool {
            return $job->to === 'user@test.com'
                && $job->subject === 'Subject'
                && $job->type === 'sendmessage';
        });
    }

    public function test_handle_drops_mail_when_smtp_disabled(): void
    {
        $this->setSmtpType('none');

        // Must not throw and must not attempt delivery.
        $this->job()->handle();
        $this->expectNotToPerformAssertions();
    }

    public function test_handle_sends_through_external_transport(): void
    {
        $this->setSmtpType('external');

        $repo = Mockery::mock(ToolRepositoryInterface::class);
        $repo->shouldReceive('sendMail')
            ->with('user@test.com', 'Subject', 'Body')
            ->once()
            ->andReturn(true);
        $this->app->instance(ToolRepositoryInterface::class, $repo);

        $this->job()->handle();
    }

    public function test_handle_throws_when_delivery_fails(): void
    {
        $this->setSmtpType('external');

        $repo = Mockery::mock(ToolRepositoryInterface::class);
        $repo->shouldReceive('sendMail')->andReturn(false);
        $this->app->instance(ToolRepositoryInterface::class, $repo);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test');

        $this->job()->handle();
    }
}
