<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\AttachmentMutationService;
use App\Support\Attachment\AttachmentService;
use App\Support\Settings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Mockery\MockInterface;
use Tests\Attributes\TestCategory;
use Tests\TestCase;

/**
 * Unit tests for AttachmentMutationService.
 *
 * Covers processUpload: null/missing file, zero-size, empty name,
 * count limit reached, file too big, banned extension, disallowed
 * extension, dangerous MIME type, valid non-image file (local driver
 * move failure), and callback function script generation.
 */
#[TestCategory(TestCategory::SERVICE_INTEGRATION)]
final class AttachmentMutationServiceTest extends TestCase
{
    use DatabaseTransactions;

    /** @var array<string, string> */
    private array $lang;

    private string $tmpDir;

    private string $saveDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::table('attachments')->delete();

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

        if ($this->saveDir !== '' && is_dir(\ROOT_PATH.$this->saveDir)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(\ROOT_PATH.$this->saveDir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST,
            );
            foreach ($iterator as $path) {
                $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
            }
            @rmdir(\ROOT_PATH.$this->saveDir);
        }

        request()->attributes->set('csp_nonce', '');
        Settings::resetCache();
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

    /**
     * Persist settings rows and drop the static caches so SiteConfig
     * picks them up inside processUpload.
     *
     * @param  array<string, string>  $nameValues
     */
    private function setSettings(array $nameValues): void
    {
        foreach ($nameValues as $name => $value) {
            DB::table('settings')->updateOrInsert(['name' => $name], ['value' => $value, 'autoload' => 1]);
        }
        Settings::resetCache();
    }

    /** @param  array<int, string>  $names */
    private function forgetSettings(array $names): void
    {
        DB::table('settings')->whereIn('name', $names)->delete();
        Settings::resetCache();
    }

    /**
     * Point the local-driver upload path at a scratch directory under
     * ROOT_PATH and configure the resizebigimg flow that abandons the
     * original file (skipping move_uploaded_file, which cannot succeed
     * in tests).
     */
    private function configureLocalImageUpload(): void
    {
        $this->saveDir = 'attachment_test_'.bin2hex(random_bytes(4));
        $this->setSettings([
            'attachment.savedirectory' => $this->saveDir,
            'attachment.savedirectorytype' => 'monthdir',
            'attachment.httpdirectory' => 'attachments',
            'attachment.thumbnailtype' => 'resizebigimg',
            'attachment.watermarkpos' => 'no',
            'image_hosting.driver' => 'local',
        ]);
    }

    /**
     * Create a real image file via GD so getimagesize() works.
     *
     * @return array<string, mixed>
     */
    private function makeImageFile(int $width, int $height): array
    {
        $path = $this->tmpDir.'/img_'.uniqid().'.jpg';
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagejpeg($image, $path, 90);

        return [
            'tmp_name' => $path,
            'size' => (int) filesize($path),
            'type' => 'image/jpeg',
            'name' => 'photo.jpg',
        ];
    }

    /** @return array{0: int, 1: int} */
    private function imageDims(string $path): array
    {
        $size = getimagesize($path);
        if ($size === false) {
            return [0, 0];
        }

        return [(int) $size[0], (int) $size[1]];
    }

    // --- successful image upload: resizebigimg abandons the original, so
    // --- the insert + callback-script tail is reachable without HTTP upload

    public function test_process_upload_resizes_landscape_image_and_inserts_attachment(): void
    {
        $this->configureLocalImageUpload();
        // Drop seeded rows so the 200x200 defaults inside processUpload are exercised.
        $this->forgetSettings(['attachment.thumbwidth', 'attachment.thumbheight']);
        request()->attributes->set('csp_nonce', 'testnonce123');
        $attach = $this->mockAttachService(['count_left' => 10]);
        $file = $this->makeImageFile(800, 400);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            'preview_custom_field_image_42',
            $file,
        );

        $this->assertSame('', $result['warning']);
        $this->assertSame(9, $result['count_left']);

        $row = DB::table('attachments')->first();
        $this->assertNotNull($row);
        $this->assertSame(1, (int) $row->userid);
        $this->assertSame(200, (int) $row->width);
        $this->assertSame(1, (int) $row->isimage);
        $this->assertSame(0, (int) $row->thumb);
        $this->assertSame('local', (string) $row->driver);
        $this->assertSame('image/jpeg', (string) $row->filetype);
        $this->assertSame(32, strlen((string) $row->dlkey));
        $this->assertMatchesRegularExpression('#^20\d{4}/\d{14}[0-9a-f]{32}\.jpg$#', (string) $row->location);
        $this->assertSame((int) filesize(\ROOT_PATH.$this->saveDir.'/'.(string) $row->location), (int) $row->filesize);

        $written = \ROOT_PATH.$this->saveDir.'/'.(string) $row->location;
        $this->assertFileExists($written);
        $this->assertSame([200, 100], $this->imageDims($written));

        $this->assertStringContainsString(
            'parent.preview_custom_field_image_42("'.$row->dlkey.'", "attachments/'.$row->location.'")',
            $result['script'],
        );
        $this->assertStringContainsString('nonce="testnonce123"', $result['script']);
    }

    public function test_process_upload_resizes_portrait_image(): void
    {
        $this->configureLocalImageUpload();
        $this->forgetSettings(['attachment.thumbwidth', 'attachment.thumbheight']);
        $attach = $this->mockAttachService();
        $file = $this->makeImageFile(400, 800);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('', $result['warning']);
        $row = DB::table('attachments')->first();
        $this->assertNotNull($row);
        $this->assertSame(100, (int) $row->width);
        $written = \ROOT_PATH.$this->saveDir.'/'.(string) $row->location;
        $this->assertSame([100, 200], $this->imageDims($written));

        // Default callback emits the [attach]dlkey[/attach] tag_extimage call.
        $this->assertStringContainsString("parent.tag_extimage('[attach]{$row->dlkey}[/attach]')", $result['script']);
    }

    public function test_process_upload_altsize_uses_alt_thumbnail_dimensions(): void
    {
        $this->configureLocalImageUpload();
        $this->forgetSettings(['attachment.altthumbwidth', 'attachment.altthumbheight']);
        $attach = $this->mockAttachService();

        $landscape = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'yes',
            '',
            $this->makeImageFile(800, 400),
        );
        $this->assertSame('', $landscape['warning']);

        $portrait = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'yes',
            '',
            $this->makeImageFile(400, 800),
        );
        $this->assertSame('', $portrait['warning']);

        $this->assertSame(2, DB::table('attachments')->count());
        $first = DB::table('attachments')->orderBy('id')->first();
        $second = DB::table('attachments')->orderByDesc('id')->first();
        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame([100, 50], $this->imageDims(\ROOT_PATH.$this->saveDir.'/'.(string) $first->location));
        $this->assertSame([50, 100], $this->imageDims(\ROOT_PATH.$this->saveDir.'/'.(string) $second->location));
    }

    public function test_process_upload_daydir_savepath_uses_day_directory(): void
    {
        $this->configureLocalImageUpload();
        $this->setSettings(['attachment.savedirectorytype' => 'daydir']);
        $attach = $this->mockAttachService();
        $file = $this->makeImageFile(800, 400);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('', $result['warning']);
        $row = DB::table('attachments')->first();
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('#^20\d{6}/\d{14}[0-9a-f]{32}\.jpg$#', (string) $row->location);
    }

    public function test_process_upload_onedir_savepath_uses_flat_location(): void
    {
        $this->configureLocalImageUpload();
        $this->setSettings(['attachment.savedirectorytype' => 'onedir']);
        $attach = $this->mockAttachService();
        $file = $this->makeImageFile(800, 400);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('', $result['warning']);
        $row = DB::table('attachments')->first();
        $this->assertNotNull($row);
        $this->assertMatchesRegularExpression('#^\d{14}[0-9a-f]{32}\.jpg$#', (string) $row->location);
    }

    public function test_process_upload_with_nonlocal_driver_returns_driver_warning(): void
    {
        $this->configureLocalImageUpload();
        // The Lsky endpoint is not configured in tests, so the upload throws
        // and the exception message becomes the warning.
        $this->setSettings(['image_hosting.driver' => 'lsky']);
        $attach = $this->mockAttachService();
        $file = $this->makeImageFile(800, 400);

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertNotSame('', $result['warning']);
        $this->assertSame('', $result['script']);
        $this->assertSame(0, DB::table('attachments')->count());
    }

    // --- boundary and coercion cases on covered-but-unasserted lines ---

    public function test_process_upload_with_size_equal_to_limit_proceeds(): void
    {
        $attach = $this->mockAttachService(['size_limit' => 5000]);
        $file = $this->makeFile(str_repeat('a', 5000), 'txt');
        $file['size'] = 5000;

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('Cannot move file.', $result['warning']);
    }

    public function test_process_upload_with_non_numeric_size_returns_nothing_received(): void
    {
        $attach = $this->mockAttachService();
        $file = $this->makeFile('content', 'txt');
        $file['size'] = 'not-a-number';

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('Nothing received.', $result['warning']);
    }

    public function test_process_upload_with_uppercase_extension_is_allowed(): void
    {
        $attach = $this->mockAttachService(['allowed_ext' => ['txt', 'pdf']]);
        $file = $this->makeFile('plain text content', 'txt');
        $file['name'] = 'notes.TXT';

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $this->lang,
            'no',
            '',
            $file,
        );

        $this->assertSame('Cannot move file.', $result['warning']);
    }

    public function test_process_upload_with_banned_extension_rejected_even_when_whitelisted(): void
    {
        // 'exe' in the admin allow-list must still lose to the hard ban list.
        $attach = $this->mockAttachService(['allowed_ext' => ['exe', 'txt']]);
        $file = $this->makeFile('plain text content', 'exe');

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

    public function test_process_upload_uses_lang_warning_for_null_file(): void
    {
        $attach = $this->mockAttachService();
        $lang = ['text_nothing_received' => 'Custom nothing received'];

        $result = AttachmentMutationService::processUpload(
            $this->curUser(),
            $attach,
            $lang,
            'no',
            '',
            null,
        );

        $this->assertSame('Custom nothing received', $result['warning']);
    }
}
