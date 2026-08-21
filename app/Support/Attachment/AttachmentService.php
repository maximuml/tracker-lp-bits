<?php

namespace App\Support\Attachment;

use App\Repositories\AttachmentRepository;
use App\Support\Config\AttachmentConfig;
use App\Support\Config\SiteConfig;
use App\Support\UserDisplay;

class AttachmentService
{
    public int $userid;

    public int $class;

    public int $countlimit;

    public int $countsofar = 0;

    public int $sizelimit;

    /** @var array<int, string> */
    public array $allowedext = [];

    private AttachmentConfig $attachmentConfig;

    public function __construct(int $userid)
    {
        $this->userid = $userid;
        $this->set_class();
        $this->set_count_so_far();
        $this->attachmentConfig = SiteConfig::current()->attachment;
        $this->set_count_limit();
        $this->set_size_limit();
        $this->set_allowed_ext();
    }

    public function enable_attachment(): bool
    {
        return $this->attachmentConfig->isEnabled();
    }

    public function set_class(): void
    {
        $userid = $this->userid;
        $row = UserDisplay::row($userid);
        $this->class = (int) ($row['class'] ?? 0);
    }

    public function set_count_so_far(): void
    {
        $this->countsofar = AttachmentRepository::countRecentForUser($this->userid);
    }

    public function get_count_so_far(): int
    {
        return $this->countsofar;
    }

    public function get_count_limit_class(int $class): int
    {
        $limits = [
            $this->attachmentConfig->classThreshold(4) => $this->attachmentConfig->countLimit(4),
            $this->attachmentConfig->classThreshold(3) => $this->attachmentConfig->countLimit(3),
            $this->attachmentConfig->classThreshold(2) => $this->attachmentConfig->countLimit(2),
            $this->attachmentConfig->classThreshold(1) => $this->attachmentConfig->countLimit(1),
        ];
        krsort($limits);
        foreach ($limits as $classLimit => $countLimit) {
            if ($classLimit > 0 && $class >= $classLimit && $countLimit > 0) {
                return $countLimit;
            }
        }

        return 0;
    }

    public function set_count_limit(): void
    {
        $class = $this->class;
        $countlimit = $this->get_count_limit_class($class);
        $this->countlimit = $countlimit;
    }

    public function get_count_limit(): int
    {
        return $this->countlimit;
    }

    public function get_count_left(): int
    {
        $left = $this->countlimit - $this->countsofar;

        return $left;
    }

    public function get_size_limit_class(int $class): int
    {
        $limits = [
            $this->attachmentConfig->classThreshold(4) => $this->attachmentConfig->sizeLimit(4),
            $this->attachmentConfig->classThreshold(3) => $this->attachmentConfig->sizeLimit(3),
            $this->attachmentConfig->classThreshold(2) => $this->attachmentConfig->sizeLimit(2),
            $this->attachmentConfig->classThreshold(1) => $this->attachmentConfig->sizeLimit(1),
        ];
        krsort($limits);
        foreach ($limits as $classLimit => $sizeLimit) {
            if ($classLimit > 0 && $class >= $classLimit && $sizeLimit > 0) {
                return $sizeLimit;
            }
        }

        return 0;
    }

    public function set_size_limit(): void
    {
        $class = $this->class;
        $sizelimit = $this->get_size_limit_class($class);
        $this->sizelimit = $sizelimit;
    }

    public function get_size_limit_kb(): int
    {
        return $this->sizelimit;
    }

    public function get_size_limit_byte(): int
    {
        return $this->sizelimit * 1024;
    }

    /**
     * @return array<int, string>
     */
    public function get_allowed_ext_class(int $class): array
    {
        $tiers = [
            ['class' => $this->attachmentConfig->classThreshold(1), 'ext' => $this->attachmentConfig->extensions(1)],
            ['class' => $this->attachmentConfig->classThreshold(2), 'ext' => $this->attachmentConfig->extensions(2)],
            ['class' => $this->attachmentConfig->classThreshold(3), 'ext' => $this->attachmentConfig->extensions(3)],
            ['class' => $this->attachmentConfig->classThreshold(4), 'ext' => $this->attachmentConfig->extensions(4)],
        ];

        $allowedext = [];
        foreach ($tiers as $tier) {
            if ($tier['class'] === 0 || $class >= $tier['class']) {
                $temprow = $this->extract_allowed_ext($tier['ext']);
                foreach ($temprow as $temp) {
                    $allowedext[] = $temp;
                }
            } elseif ($tier['class'] > 0) {
                break;
            }
        }

        return $allowedext;
    }

    public function set_allowed_ext(): void
    {
        $class = $this->class;
        $allowedext = $this->get_allowed_ext_class($class);
        $this->allowedext = $allowedext;
    }

    /**
     * @return array<int, string>
     */
    public function get_allowed_ext(): array
    {
        return $this->allowedext;
    }

    /**
     * @return array<int, string>
     */
    public function extract_allowed_ext(string $string): array
    {
        $string = rtrim(trim($string), ',');
        $exts = explode(',', $string);
        $extrow = [];
        foreach ($exts as $ext) {
            $extrow[] = trim($ext);
        }

        return $extrow;
    }

    public function is_gif_ani(string $filename): bool
    {
        if (! ($fh = @fopen($filename, 'rb'))) {
            return false;
        }
        $count = 0;
        // an animated gif contains multiple "frames", with each frame having a
        // header made up of:
        // * a static 4-byte sequence (\x00\x21\xF9\x04)
        // * 4 variable bytes
        // * a static 2-byte sequence (\x00\x2C)

        // We read through the file til we reach the end of the file, or we've found
        // at least 2 frame headers
        while (! feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); // read 100kb at a time
            if ($chunk === false) {
                break;
            }
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk, $matches);
        }

        return $count > 1;
    }
}
