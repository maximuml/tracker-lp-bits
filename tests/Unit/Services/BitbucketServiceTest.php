<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BitbucketService;
use App\Support\Globals;
use App\Support\Path;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for BitbucketService.
 *
 * Covers getBitbucketName (existent/nonexistent), deleteBitbucket
 * (nonexistent, record-only, with-file), and uploadAvatar (bad filename,
 * existing file, invalid image, valid PNG, public/private flag).
 */
final class BitbucketServiceTest extends TestCase
{
    use DatabaseTransactions;

    private BitbucketService $service;

    /** @var list<string> files created during tests that need cleanup */
    private array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Redis::connection()->flushdb();
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        DB::table('bitbucket')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');

        $this->service = new BitbucketService;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        Mockery::close();
        parent::tearDown();
    }

    private function insertUser(int $id = 1): int
    {
        DB::table('users')->insert([
            'id' => $id,
            'username' => 'user'.$id,
            'email' => 'user'.$id.'@test.com',
            'passhash' => 'hash',
            'secret' => 'secret',
            'passkey' => str_pad((string) $id, 32, '0'),
            'class' => 1,
            'added' => now()->toDateTimeString(),
            'last_access' => now()->toDateTimeString(),
            'status' => 'confirmed',
            'enabled' => 1,
            'parked' => 0,
            'downloadpos' => 1,
            'seedbonus' => 100.0,
        ]);

        return $id;
    }

    private function insertBitbucket(int $owner, string $name, string $public = '0'): int
    {
        return (int) DB::table('bitbucket')->insertGetId([
            'owner' => $owner,
            'name' => $name,
            'added' => date('Y-m-d H:i:s'),
            'public' => $public,
        ]);
    }

    private function bitbucketPath(string $filename): string
    {
        $bitbucket = (string) app(Globals::class)->get('bitbucket', 'bitbucket');

        return Path::resolve("{$bitbucket}/{$filename}", \ROOT_PATH);
    }

    // --- getBitbucketName ---

    public function test_get_bitbucket_name_returns_null_for_nonexistent(): void
    {
        $this->assertNull($this->service->getBitbucketName(99999));
    }

    public function test_get_bitbucket_name_returns_name_for_existing(): void
    {
        $id = $this->insertBitbucket(1, 'avatar.png');

        $this->assertSame('avatar.png', $this->service->getBitbucketName($id));
    }

    // --- deleteBitbucket ---

    public function test_delete_bitbucket_returns_true_for_nonexistent(): void
    {
        $this->assertTrue($this->service->deleteBitbucket(99999, '/tmp/bitbucket'));
    }

    public function test_delete_bitbucket_deletes_record_when_file_not_found(): void
    {
        $id = $this->insertBitbucket(1, 'gone.png');

        $result = $this->service->deleteBitbucket($id, '/tmp/nonexistent-dir');

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('bitbucket')->where('id', $id)->count());
    }

    public function test_delete_bitbucket_deletes_record_and_file(): void
    {
        $id = $this->insertBitbucket(1, 'deleteme.png');

        // Create the actual file in a temp dir
        $dir = sys_get_temp_dir().'/bitbucket_test_'.uniqid();
        mkdir($dir, 0777, true);
        $file = $dir.'/deleteme.png';
        file_put_contents($file, 'test');
        $this->createdFiles[] = $file;

        $result = $this->service->deleteBitbucket($id, $dir);

        $this->assertTrue($result);
        $this->assertSame(0, DB::table('bitbucket')->where('id', $id)->count());
        $this->assertFalse(file_exists($file));

        @rmdir($dir);
    }

    // --- uploadAvatar: error cases ---

    public function test_upload_avatar_throws_for_bad_filename_with_path_traversal(): void
    {
        // Symfony's UploadedFile strips directory separators from the original
        // name, so we mock it to simulate a path-traversal attack.
        $tempFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tempFile, 'test');
        if ($tempFile !== false) {
            $this->createdFiles[] = $tempFile;
        }

        $file = Mockery::mock(UploadedFile::class);
        $file->shouldReceive('getClientOriginalName')->andReturn('../malicious.png');
        $file->shouldReceive('getPathname')->andReturn($tempFile);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Bad file name.');

        /** @var UploadedFile $file */
        $this->service->uploadAvatar($file, ['id' => 1], false);
    }

    public function test_upload_avatar_throws_for_existing_file(): void
    {
        $filename = 'duplicate_test_'.uniqid().'.png';
        $targetPath = $this->bitbucketPath($filename);
        file_put_contents($targetPath, 'existing');
        $this->createdFiles[] = $targetPath;

        $file = UploadedFile::fake()->image($filename, 100, 100);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('File already exists');

        $this->service->uploadAvatar($file, ['id' => 1], false);
    }

    public function test_upload_avatar_throws_for_invalid_image_format(): void
    {
        $filename = 'not_an_image_'.uniqid().'.png';
        $file = UploadedFile::fake()->createWithContent($filename, 'this is not an image');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid image format.');

        $this->service->uploadAvatar($file, ['id' => 1], false);
    }

    public function test_upload_avatar_throws_for_extension_mismatch(): void
    {
        // Create a real PNG image but give it a .gif extension
        $tempFile = tempnam(sys_get_temp_dir(), 'test').'.png';
        $img = imagecreatetruecolor(100, 100);
        imagepng($img, $tempFile);
        imagedestroy($img);
        $this->createdFiles[] = $tempFile;
        $file = new UploadedFile($tempFile, 'mismatch_'.uniqid().'.gif', 'image/gif', null, true);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid image format.');

        $this->service->uploadAvatar($file, ['id' => 1], false);
    }

    // --- uploadAvatar: success cases ---

    public function test_upload_avatar_succeeds_for_valid_png(): void
    {
        $this->insertUser(1);
        $filename = 'valid_test_'.uniqid().'.png';
        $file = UploadedFile::fake()->image($filename, 300, 400);

        $targetPath = $this->bitbucketPath($filename);
        $this->createdFiles[] = $targetPath;

        $result = $this->service->uploadAvatar($file, ['id' => 1], true);

        $this->assertSame($filename, $result['filename']);
        $this->assertSame(300, $result['width']);
        $this->assertSame(400, $result['height']);
        $this->assertGreaterThan(0, $result['newwidth']);
        $this->assertGreaterThan(0, $result['newheight']);
        $this->assertNotEmpty($result['url']);

        // DB record should exist
        $this->assertSame(1, DB::table('bitbucket')->where('name', $filename)->count());
        $record = DB::table('bitbucket')->where('name', $filename)->first();
        $this->assertNotNull($record);
        $this->assertSame(1, (int) $record->owner);
        $this->assertSame('1', $record->public);
    }

    public function test_upload_avatar_updates_user_avatar(): void
    {
        $this->insertUser(1);
        $filename = 'avatar_update_'.uniqid().'.png';
        $file = UploadedFile::fake()->image($filename, 200, 200);

        $this->createdFiles[] = $this->bitbucketPath($filename);

        $this->service->uploadAvatar($file, ['id' => 1], false);

        $avatar = (string) DB::table('users')->where('id', 1)->value('avatar');
        $this->assertNotEmpty($avatar);
        $this->assertStringContainsString($filename, $avatar);
    }

    public function test_upload_avatar_private_flag_sets_public_zero(): void
    {
        $this->insertUser(1);
        $filename = 'private_test_'.uniqid().'.png';
        $file = UploadedFile::fake()->image($filename, 100, 100);

        $this->createdFiles[] = $this->bitbucketPath($filename);

        $this->service->uploadAvatar($file, ['id' => 1], false);

        $record = DB::table('bitbucket')->where('name', $filename)->first();
        $this->assertNotNull($record);
        $this->assertSame('0', $record->public);
    }

    public function test_upload_avatar_succeeds_for_valid_gif(): void
    {
        $this->insertUser(1);
        $filename = 'valid_gif_'.uniqid().'.gif';
        $file = UploadedFile::fake()->image($filename, 100, 100);

        $this->createdFiles[] = $this->bitbucketPath($filename);

        $result = $this->service->uploadAvatar($file, ['id' => 1], true);

        $this->assertSame($filename, $result['filename']);
        $this->assertSame(1, DB::table('bitbucket')->where('name', $filename)->count());
    }
}
