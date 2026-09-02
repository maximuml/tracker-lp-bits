<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AttachmentMutationService;
use App\Support\Attachment\AttachmentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Unit tests for AttachmentMutationService.
 *
 * Covers processUpload: null/missing file, zero-size, empty name,
 * count limit reached, file too big, banned extension, disallowed
 * extension, dangerous MIME type, valid non-image file (local driver
 * move failure), and callback function script generation.
 */
final class AttachmentMutationServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, string> */
    private array $lang;

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('attachments')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->lang = [
            'text_nothing_received' => 'Nothing received.',
            'text_file_number_limit_reached' => 'File number limit reached.',
            'text_file_size_too_big' => 'File size too big.',
            'text_file_extension_not_allowed' => 'File extension not allowed.',
            'text_invalid_image_file' => 'Invalid image file.',
            'text_cannot_move_file' => 'Cannot move file.',
        ];

        $this->tmpDir = sys_get_temp_dir().'/attachment_test_'.uniqid();
        if (! is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir.'/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->tmpDir);
        }

        Mockery::close();
        parent::tearDown();
    }

    /**
     * Create a mock AttachmentService with configurable limits.
     *
     * @param  array{count_left?: int, size_limit?: int, allowed_ext?: array<int, string>}  $config
     * @return AttachmentService&MockInterface
     */
    private function mockAttachService(array $config = []): mixed
    {
        /** @var AttachmentService&MockInterface $attach */
        $attach = Mockery::mock(AttachmentService::class);
        $attach->shouldReceive('get_count_left')->andReturn($config['count_left'] ?? 10);
        $attach->shouldReceive('get_size_limit_byte')->andReturn($config['size_limit'] ?? 10485760);
        $attach->shouldReceive('get_allowed_ext')->andReturn($config['allowed_ext'] ?? ['txt', 'pdf', 'zip', 'jpg', 'png', 'gif']);
        $attach->shouldReceive('is_gif_ani')->andReturn(false);

        return $attach;
    }

    /** @return array<string, mixed> */
    private function curUser(): array
    {
        return ['id' => 1, 'username' => 'testuser', 'class' => 1];
    }

    /**
     * Create a real temp file with the given content and extension.
     *
     * @return array<string, mixed>
     */
    private function makeFile(string $content, string $ext, int $size = 0): array
    {
        $path = $this->tmpDir.'/test_'.uniqid().'.'.$ext;
        file_put_contents($path, $content);

        return [
            'tmp_name' => $path,
            'size' => $size > 0 ? $size : strlen($content),
            'type' => 'application/octet-stream',
            'name' => 'upload.'.$ext,
        ];
    }

    // --- null file ---

    public function test_process_upload_with_null_file_returns_nothing_received_warning(): void
    {
        $attach = $this->mockAttachService();

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            null,
        );

        $this->assertSame('Nothing received.', $result['warning']);
        $this->assertSame('', $result['script']);
        $this->assertSame(10, $result['count_left']);
    }

    // --- file with missing keys ---

    public function test_process_upload_with_missing_file_keys_returns_nothing_received_warning(): void
    {
        $attach = $this->mockAttachService();

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            ['tmp_name' => '/tmp/foo'],
        );

        $this->assertSame('Nothing received.', $result['warning']);
        $this->assertSame(10, $result['count_left']);
    }

    // --- zero file size ---

    public function test_process_upload_with_zero_file_size_returns_nothing_received_warning(): void
    {
        $attach = $this->mockAttachService();
        $file = $this->makeFile('content', 'txt');
        $file['size'] = 0;

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('Nothing received.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- empty file name ---

    public function test_process_upload_with_empty_name_returns_nothing_received_warning(): void
    {
        $attach = $this->mockAttachService();
        $file = $this->makeFile('content', 'txt');
        $file['name'] = '';

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('Nothing received.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- count limit reached ---

    public function test_process_upload_with_zero_count_left_returns_limit_warning(): void
    {
        $attach = $this->mockAttachService(['count_left' => 0]);
        $file = $this->makeFile('content', 'txt');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File number limit reached.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- file too big (exceeds size limit) ---

    public function test_process_upload_exceeding_size_limit_returns_too_big_warning(): void
    {
        $attach = $this->mockAttachService(['size_limit' => 100]);
        $file = $this->makeFile('content', 'txt');
        $file['size'] = 200;

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File size too big.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- file too big (exceeds 5MB hard limit) ---

    public function test_process_upload_exceeding_5mb_hard_limit_returns_too_big_warning(): void
    {
        $attach = $this->mockAttachService(['size_limit' => 99999999]);
        $file = $this->makeFile('content', 'txt');
        $file['size'] = 5242880;

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File size too big.', $result['warning']);
    }

    // --- banned extension ---

    public function test_process_upload_with_banned_extension_returns_not_allowed_warning(): void
    {
        $attach = $this->mockAttachService(['allowed_ext' => ['php']]);
        $file = $this->makeFile('<?php echo 1;', 'php');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File extension not allowed.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- disallowed extension (not in allowed list) ---

    public function test_process_upload_with_disallowed_extension_returns_not_allowed_warning(): void
    {
        $attach = $this->mockAttachService(['allowed_ext' => ['txt', 'pdf']]);
        $file = $this->makeFile('binary content', 'exe');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File extension not allowed.', $result['warning']);
    }

    // --- dangerous MIME type (PHP content with allowed extension) ---

    public function test_process_upload_with_dangerous_mime_type_returns_not_allowed_warning(): void
    {
        $attach = $this->mockAttachService(['allowed_ext' => ['txt', 'php', 'exe']]);
        $file = $this->makeFile('<?php phpinfo(); ?>', 'txt');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('File extension not allowed.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- valid non-image file (local driver: move_uploaded_file fails in test) ---

    public function test_process_upload_valid_non_image_file_returns_cannot_move_warning(): void
    {
        $attach = $this->mockAttachService();
        $file = $this->makeFile('plain text content', 'txt');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        // move_uploaded_file fails in test context (not a real HTTP upload)
        $this->assertSame('Cannot move file.', $result['warning']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- valid non-image file with callback function ---

    public function test_process_upload_with_callback_function_still_fails_move_in_test(): void
    {
        $attach = $this->mockAttachService();
        $file = $this->makeFile('plain text content', 'txt');

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            'preview_custom_field_image_1',
            $file,
        );

        // Move fails in test context, so no script is generated
        $this->assertSame('Cannot move file.', $result['warning']);
        $this->assertSame('', $result['script']);
    }

    // --- count_left is returned in early-exit paths ---

    public function test_process_upload_early_exit_preserves_count_left(): void
    {
        $attach = $this->mockAttachService(['count_left' => 5]);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            null,
        );

        $this->assertSame(5, $result['count_left']);
    }

    // --- empty lang array uses defaults ---

    public function test_process_upload_with_empty_lang_uses_default_warning(): void
    {
        $attach = $this->mockAttachService();

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            [],
            'no',
            '',
            null,
        );

        $this->assertSame('Nothing received.', $result['warning']);
    }
}
