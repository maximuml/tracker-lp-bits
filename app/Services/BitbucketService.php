<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BitbucketPublic;
use App\Repositories\BitbucketRepository;
use App\Repositories\UserAccountRepository;
use App\Support\Config\SiteConfig;
use App\Support\Path;
use App\Support\Url;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Handles bitbucket (avatar) image upload and deletion.
 *
 * Image validation, resizing, and DB record management are encapsulated here.
 * The controller handles HTTP concerns (auth, lang, view rendering).
 */
final class BitbucketService
{
    private const SCALE_HEIGHT = 200;

    private const SCALE_WIDTH = 150;

    public function __construct(
        private readonly BitbucketRepository $bitbucketRepository,
        private readonly UserAccountRepository $userAccountRepository,
    ) {}

    /**
     * Process an uploaded avatar image: validate, resize, save, and update DB.
     *
     * @param  array<string, mixed>  $currentUser
     * @return array{url: string, filename: string, width: int, height: int, newwidth: int, newheight: int}
     *
     * @throws LogicException On invalid image or processing failure.
     */
    public function uploadAvatar(UploadedFile $file, array $currentUser, bool $isPublic): array
    {
        $filename = $file->getClientOriginalName();
        $filename = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $filename);
        $pp = pathinfo($filename);
        if ($pp['basename'] !== $filename) {
            throw new LogicException('Bad file name.');
        }

        $bitbucket = SiteConfig::current()->main->bitbucket('bitbucket');
        $bucketDir = Path::resolve($bitbucket, public_path());
        if (! is_dir($bucketDir) && ! mkdir($bucketDir, 0775, true) && ! is_dir($bucketDir)) {
            throw new LogicException('Upload directory is not writable.');
        }
        $tgtfile = $bucketDir.'/'.$filename;
        if (file_exists($tgtfile)) {
            throw new LogicException('File already exists: '.$filename);
        }

        $size = getimagesize($file->getPathname());
        if ($size === false) {
            throw new LogicException('Invalid image format.');
        }

        $height = (int) $size[1];
        $width = (int) $size[0];
        $it = (int) $size[2];
        $imgtypes = [null, 'gif', 'jpg', 'png'];
        $typeName = $imgtypes[$it] ?? null;

        if ($typeName === null || $typeName !== strtolower($pp['extension'] ?? '')) {
            throw new LogicException('Invalid image format.');
        }

        $hscale = $height / self::SCALE_HEIGHT;
        $wscale = $width / self::SCALE_WIDTH;
        $scale = ($hscale < 1 && $wscale < 1) ? 1 : (($hscale > $wscale) ? $hscale : $wscale);
        $newwidth = max(1, (int) floor($width / $scale));
        $newheight = max(1, (int) floor($height / $scale));

        $orig = match ($it) {
            1 => @imagecreatefromgif($file->getPathname()),
            2 => @imagecreatefromjpeg($file->getPathname()),
            default => @imagecreatefrompng($file->getPathname()),
        };

        if (! $orig) {
            throw new LogicException('Image processing failed.');
        }

        $thumb = imagecreatetruecolor($newwidth, $newheight);
        if ($thumb === false) {
            throw new LogicException('Thumbnail creation failed.');
        }
        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);

        $written = match ($it) {
            1 => imagegif($thumb, $tgtfile),
            2 => imagejpeg($thumb, $tgtfile),
            default => imagepng($thumb, $tgtfile),
        };
        if (! $written || ! file_exists($tgtfile)) {
            throw new LogicException('Image processing failed.');
        }

        $baseUrl = SiteConfig::current()->basic->baseUrl();
        $url = str_replace(' ', '%20', htmlspecialchars(Url::absolute($baseUrl)."/{$bitbucket}/{$filename}"));
        $public = $isPublic ? BitbucketPublic::YES->value : BitbucketPublic::NO->value;

        $this->bitbucketRepository->insert([
            'owner' => $currentUser['id'],
            'name' => $filename,
            'added' => date('Y-m-d H:i:s'),
            'public' => $public,
        ]);

        $this->userAccountRepository->updateById((int) $currentUser['id'], ['avatar' => $url]);

        return [
            'url' => $url,
            'filename' => $filename,
            'width' => $width,
            'height' => $height,
            'newwidth' => $newwidth,
            'newheight' => $newheight,
        ];
    }

    /**
     * Delete a bitbucket image and its DB record.
     *
     * @return bool True on success, false if file cannot be deleted.
     */
    public function deleteBitbucket(int $id, string $bucketPath): bool
    {
        $bitbucket = $this->bitbucketRepository->findColumnsById($id, ['name', 'owner']);
        if (! $bitbucket) {
            return true;
        }

        $file = $bucketPath.'/'.$bitbucket->name;
        $this->bitbucketRepository->deleteById($id);

        if (file_exists($file) && ! unlink($file)) {
            return false;
        }

        return true;
    }

    /**
     * Get the name of a bitbucket entry (for error messages).
     */
    public function getBitbucketName(int $id): ?string
    {
        $row = $this->bitbucketRepository->findColumnsById($id, ['name']);

        return $row ? (string) $row->name : null;
    }
}
