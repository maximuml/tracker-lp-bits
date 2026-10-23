<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTOs\Auth\ActorContext;
use App\Exceptions\NexusException;
use App\Exceptions\UploadValidationException;
use App\Models\User;
use App\Services\UploadService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Attributes\TestCategory;
use Tests\Concerns\MakesCurrentUser;
use Tests\TestCase;

/**
 * Pins UploadService entry gates — authentication and the first
 * request validations that run before any torrent work.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class UploadServiceTest extends TestCase
{
    use DatabaseTransactions;
    use MakesCurrentUser;

    /** @param  array<string, mixed>  $actorData */
    private function service(array $actorData): UploadService
    {
        $this->app->instance(ActorContext::class, $this->actor($actorData));

        return app(UploadService::class);
    }

    public function test_upload_rejects_guest(): void
    {
        $request = Request::create('/takeupload', 'POST', ['descr' => 'x']);

        $this->expectException(NexusException::class);
        $this->expectExceptionMessage('Unauthenticated');

        $this->service([])->upload($request);
    }

    public function test_upload_rejects_blank_description(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $request = Request::create('/takeupload', 'POST', ['descr' => '', 'type' => 1]);

        try {
            $this->service(['id' => $user->id])->upload($request);
            $this->fail('expected UploadValidationException');
        } catch (UploadValidationException $e) {
            $this->assertSame('descr', $e->field());
        }
    }

    public function test_upload_rejects_missing_category(): void
    {
        /** @var User $user */
        $user = User::factory()->create();

        $request = Request::create('/takeupload', 'POST', ['descr' => 'description']);

        try {
            $this->service(['id' => $user->id])->upload($request);
            $this->fail('expected UploadValidationException');
        } catch (UploadValidationException $e) {
            $this->assertSame('type', $e->field());
        }
    }
}
