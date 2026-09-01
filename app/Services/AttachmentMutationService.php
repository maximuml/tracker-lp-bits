<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Attachment;
use App\Support\Attachment\AttachmentService;
use App\Support\AttachmentStorage;
use App\Support\Config\SiteConfig;
use App\Support\Globals;
use App\Support\Logger;
use App\Support\Path;
use Illuminate\Support\Facades\DB;

class AttachmentMutationService
{
    /**
     * @param  array<string, mixed>  $CURUSER
     * @param  array<string, string>  $lang
     * @param  array<string, mixed>|null  $file
     * @return array{warning: string, script: string, count_left: int}
     */
    public static function processUpload(array $CURUSER, AttachmentService $Attach, array $lang, string $altsize, string $callbackFunc, ?array $file): array
    {
        $warning = '';
        $script = '';
        $count_left = $Attach->get_count_left();
        $size_limit = $Attach->get_size_limit_byte();
        $allowed_exts = $Attach->get_allowed_ext();

        if ($file === null || ! isset($file['tmp_name'], $file['size'], $file['type'], $file['name'])) {
            return ['warning' => $lang['text_nothing_received'] ?? 'Nothing received.', 'script' => '', 'count_left' => $count_left];
        }

        $savedirectorytype_attachment = (string) (app(Globals::class)->get('savedirectorytype_attachment') ?? 'monthdir');
        $savedirectory_attachment = (string) (app(Globals::class)->get('savedirectory_attachment') ?? 'attachments');
        $httpdirectory_attachment = (string) (app(Globals::class)->get('httpdirectory_attachment') ?? 'attachments');
        $thumbnailtype_attachment = (string) (app(Globals::class)->get('thumbnailtype_attachment') ?? 'createthumb');
        $thumbwidth_attachment = (int) (app(Globals::class)->get('thumbwidth_attachment') ?? 200);
        $thumbheight_attachment = (int) (app(Globals::class)->get('thumbheight_attachment') ?? 200);
        $thumbquality_attachment = (int) (app(Globals::class)->get('thumbquality_attachment') ?? 80);
        $watermarkpos_attachment = (string) (app(Globals::class)->get('watermarkpos_attachment') ?? 'no');
        $watermarkwidth_attachment = (int) (app(Globals::class)->get('watermarkwidth_attachment') ?? 100);
        $watermarkheight_attachment = (int) (app(Globals::class)->get('watermarkheight_attachment') ?? 100);
        $watermarkquality_attachment = (int) (app(Globals::class)->get('watermarkquality_attachment') ?? 90);
        $altthumbwidth_attachment = (int) (app(Globals::class)->get('altthumbwidth_attachment') ?? 100);
        $altthumbheight_attachment = (int) (app(Globals::class)->get('altthumbheight_attachment') ?? 100);

        $isimage = false;
        $width = 0;
        $height = 0;
        $it = 0;
        $orig = false;
        $thumb = false;
        $resource = false;
        $wmx = 0;
        $wmy = 0;
        $location = '';
        $url = '';
        $hasthumb = false;
        $abandonorig = false;
        $maycreatethumb = false;
        $stop = false;
        $savepath = '';
        $filemd5 = '';
        $filename = '';
        $file_location = '';
        $db_file_location = '';

        /* $file passed as parameter */
        $filesize = (int) $file['size'];
        $filetype = (string) $file['type'];
        $origfilename = (string) $file['name'];
        $ext_l = strrpos($origfilename, '.');
        if ($ext_l === false) {
            $ext = '';
        } else {
            $ext = strtolower(substr($origfilename, $ext_l + 1, strlen($origfilename) - ($ext_l + 1)));
        }
        $banned_ext = [
            'exe', 'com', 'bat', 'msi',
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phtm', 'pht', 'phps',
            'pl', 'py', 'sh', 'cgi', 'cmd', 'scr', 'vbs', 'wsf',
            'html', 'htm', 'xhtml', 'shtml', 'js', 'css',
            'htaccess', 'htpasswd', 'ini', 'json', 'log',
            'jsp', 'asp', 'aspx', 'asa', 'cer', 'xml', 'svg',
        ];
        $img_ext = Attachment::IMG_EXTENSIONS;

        if ($filesize == 0 || $file['name'] == '') { // nothing received
            $warning = $lang['text_nothing_received'];
        } elseif (! $count_left) { // user cannot upload more files
            $warning = $lang['text_file_number_limit_reached'];
        } elseif ($filesize > $size_limit || $filesize >= 5242880) { // do not allow file bigger than 5 MB
            $warning = $lang['text_file_size_too_big'];
        } elseif (! in_array($ext, $allowed_exts) || in_array($ext, $banned_ext)) { // the file extension is banned
            $warning = $lang['text_file_extension_not_allowed'];
        } elseif (self::isDangerousMimeType($file['tmp_name'])) { // actual file content is a script/executable
            $warning = $lang['text_file_extension_not_allowed'];
        } else { // everythins is okay
            if (in_array($ext, $img_ext)) {
                $isimage = true;
            } else {
                $isimage = false;
            }
            $width = $height = 0;
            $imagesize = [];
            if ($isimage) {
                $imagesize = getimagesize($file['tmp_name']);
                if ($imagesize === false) {
                    $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                    return compact('warning', 'script', 'count_left');
                }
                $height = (int) $imagesize[1];
                $width = (int) $imagesize[0];
                if ($width <= 0 || $height <= 0) {
                    $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                    return compact('warning', 'script', 'count_left');
                }
            }
            // get driver
            $storageDriver = SiteConfig::current()->imageHosting->driver('local');
            if ($storageDriver == 'local' || ! $isimage) {
                if ($savedirectorytype_attachment == 'onedir') {
                    $savepath = '';
                } elseif ($savedirectorytype_attachment == 'monthdir') {
                    $savepath = date('Ym').'/';
                } elseif ($savedirectorytype_attachment == 'daydir') {
                    $savepath = date('Ymd').'/';
                }
                $filemd5 = md5_file($file['tmp_name']);
                $filename = date('YmdHis').$filemd5;
                $file_location = Path::makeFolder($savedirectory_attachment.'/', $savepath, \ROOT_PATH).$filename;
                Logger::writeWithContext((string) "file_location: {$file_location}", 'info');
                $db_file_location = $savepath.$filename;
                $abandonorig = false;
                $hasthumb = false;
                if ($isimage) { // the uploaded file is a image
                    $maycreatethumb = false;
                    $stop = false;
                    if ($imagesize) {
                        $it = $imagesize[2];
                        if ($it != 1 || ! $Attach->is_gif_ani($file['tmp_name'])) { // if it is an animation GIF, stop creating thumbnail and adding watermark
                            if ($thumbnailtype_attachment != 'no') { // create thumbnail for big image
                                // determine the size of thumbnail
                                if ($altsize == 'yes') {
                                    $targetwidth = $altthumbwidth_attachment;
                                    $targetheight = $altthumbheight_attachment;
                                } else {
                                    $targetwidth = $thumbwidth_attachment;
                                    $targetheight = $thumbheight_attachment;
                                }
                                $hscale = $height / $targetheight;
                                $wscale = $width / $targetwidth;
                                $scale = ($hscale < 1 && $wscale < 1) ? 1 : (($hscale > $wscale) ? $hscale : $wscale);
                                $newwidth = max(1, (int) floor($width / $scale));
                                $newheight = max(1, (int) floor($height / $scale));
                                if ($scale != 1) { // thumbnail is needed
                                    if ($it == 1) {
                                        $orig = @imagecreatefromgif($file['tmp_name']);
                                    } elseif ($it == 2) {
                                        $orig = @imagecreatefromjpeg($file['tmp_name']);
                                    } else {
                                        $orig = @imagecreatefrompng($file['tmp_name']);
                                    }
                                    if ($orig) {
                                        $thumb = imagecreatetruecolor($newwidth, $newheight);
                                        if ($thumb === false) {
                                            $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                                            return compact('warning', 'script', 'count_left');
                                        }
                                        imagecopyresampled($thumb, $orig, 0, 0, 0, 0, $newwidth, $newheight, $width, $height);
                                        if ($thumbnailtype_attachment == 'createthumb') {
                                            $hasthumb = true;
                                            imagejpeg($thumb, $file_location.'.'.$ext.'.thumb.jpg', $thumbquality_attachment);
                                        } elseif ($thumbnailtype_attachment == 'resizebigimg') {
                                            $ext = 'jpg';
                                            $filetype = 'image/jpeg';
                                            $it = 2;
                                            $height = $newheight;
                                            $width = $newwidth;
                                            $maycreatethumb = true;
                                            $abandonorig = true;
                                        }
                                    }
                                }
                            }
                            $watermarkpos = $watermarkpos_attachment;
                            if ($watermarkpos != 'no') { // add watermark to image
                                if ($width > $watermarkwidth_attachment && $height > $watermarkheight_attachment) {
                                    if ($abandonorig && $thumb) {
                                        $resource = $thumb;
                                    } else {
                                        $resource = imagecreatetruecolor($width, $height);
                                        if ($resource === false) {
                                            $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                                            return compact('warning', 'script', 'count_left');
                                        }
                                        if ($it == 1) {
                                            $resource_p = @imagecreatefromgif($file['tmp_name']);
                                        } elseif ($it == 2) {
                                            $resource_p = @imagecreatefromjpeg($file['tmp_name']);
                                        } else {
                                            $resource_p = @imagecreatefrompng($file['tmp_name']);
                                        }
                                        if ($resource_p === false) {
                                            $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                                            return compact('warning', 'script', 'count_left');
                                        }
                                        imagecopy($resource, $resource_p, 0, 0, 0, 0, $width, $height);
                                    }
                                    $watermark = imagecreatefrompng('pic/watermark.png');
                                    if ($watermark === false) {
                                        $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                                        return compact('warning', 'script', 'count_left');
                                    }
                                    $watermark_width = imagesx($watermark);
                                    $watermark_height = imagesy($watermark);
                                    // the position of the watermark
                                    if ($watermarkpos == 'random') {
                                        $watermarkpos = mt_rand(1, 9);
                                    }
                                    switch ($watermarkpos) {
                                        case 1:
                                            $wmx = 5;
                                            $wmy = 5;
                                            break;

                                        case 2:
                                            $wmx = ($width - $watermark_width) / 2;
                                            $wmy = 5;
                                            break;

                                        case 3:
                                            $wmx = $width - $watermark_width - 5;
                                            $wmy = 5;
                                            break;

                                        case 4:
                                            $wmx = 5;
                                            $wmy = ($height - $watermark_height) / 2;
                                            break;

                                        case 5:
                                            $wmx = ($width - $watermark_width) / 2;
                                            $wmy = ($height - $watermark_height) / 2;
                                            break;

                                        case 6:
                                            $wmx = $width - $watermark_width - 5;
                                            $wmy = ($height - $watermark_height) / 2;
                                            break;

                                        case 7:
                                            $wmx = 5;
                                            $wmy = $height - $watermark_height - 5;
                                            break;

                                        case 8:
                                            $wmx = ($width - $watermark_width) / 2;
                                            $wmy = $height - $watermark_height - 5;
                                            break;

                                        case 9:
                                            $wmx = $width - $watermark_width - 5;
                                            $wmy = $height - $watermark_height - 5;
                                            break;

                                    }

                                    imagecopy($resource, $watermark, $wmx, $wmy, 0, 0, $watermark_width, $watermark_height);
                                    if ($it == 1) {
                                        imagegif($resource, $file_location.'.'.$ext);
                                    } elseif ($it == 2) {
                                        imagejpeg($resource, $file_location.'.'.$ext, $watermarkquality_attachment);
                                    } else {
                                        imagepng($resource, $file_location.'.'.$ext);
                                    }
                                    $filesize = filesize($file_location.'.'.$ext);
                                    $maycreatethumb = false;
                                    $abandonorig = true;
                                }
                            }
                            if ($maycreatethumb) { // if no watermark is added, create the thumbnail now for the above resized image.
                                if ($thumb === false) {
                                    $warning = $lang['text_invalid_image_file'] ?? 'Invalid image file.';

                                    return compact('warning', 'script', 'count_left');
                                }
                                imagejpeg($thumb, $file_location.'.'.$ext, $thumbquality_attachment);
                                $filesize = filesize($file_location.'.'.$ext);
                            }
                        }
                    } else {
                        $warning = $lang['text_invalid_image_file'];
                    }
                }
                if (! $abandonorig) {
                    if (! move_uploaded_file($file['tmp_name'], $file_location.'.'.$ext)) {
                        $warning = $lang['text_cannot_move_file'];
                    }
                }
                $url = $httpdirectory_attachment.'/'.$db_file_location.".$ext";
                if ($hasthumb) {
                    $url .= '.thumb.jpg';
                }
                $location = $db_file_location.'.'.$ext;
            } else {
                try {
                    $driver = AttachmentStorage::driver();
                    $location = $driver->uploadGetLocation($file['tmp_name'], $file['name']);
                    Logger::writeWithContext((string) "location: {$location}", 'info');
                    $url = $driver->getImageUrl($location);
                } catch (\Exception $exception) {
                    Logger::writeWithContext((string) ('upload failed: '.$exception->getMessage().$exception->getTraceAsString()), 'error');
                    $warning = $exception->getMessage();
                }
            }
            if (! $warning) { // insert into database and add code to editor
                $dlkey = bin2hex(random_bytes(16));
                DB::table('attachments')->insert([
                    'userid' => $CURUSER['id'],
                    'width' => $width,
                    'added' => date('Y-m-d H:i:s'),
                    'filename' => $origfilename,
                    'filetype' => $filetype,
                    'filesize' => $filesize,
                    'location' => $location,
                    'dlkey' => $dlkey,
                    'isimage' => $isimage ? 1 : 0,
                    'thumb' => $hasthumb ? 1 : 0,
                    'driver' => $storageDriver,
                ]);
                $count_left--;
                if (! empty($callbackFunc) && preg_match('/^preview_custom_field_image_\d+$/', $callbackFunc)) {
                    $script = sprintf('<script type="text/javascript">parent.%s("%s", "%s")</script>', $callbackFunc, $dlkey, addslashes($url));
                } else {
                    $script = "<script type=\"text/javascript\">parent.tag_extimage('[attach]".$dlkey."[/attach]');</script>";
                }
            }
        }

        return compact('warning', 'script', 'count_left');
    }

    /**
     * Reject files whose actual content is a script/executable regardless of
     * the allowed extension list configured by an admin.
     */
    private static function isDangerousMimeType(string $tmpName): bool
    {
        if (! file_exists($tmpName)) {
            return false;
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo !== false ? finfo_file($finfo, $tmpName) : false;
            if ($finfo !== false) {
                finfo_close($finfo);
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($tmpName);
        } else {
            $mime = false;
        }

        if ($mime === false) {
            return false;
        }

        $dangerous = [
            'text/x-php', 'application/x-php', 'application/php',
            'text/x-perl', 'text/x-python', 'application/x-python',
            'application/x-sh', 'application/x-shellscript',
            'application/x-msdownload', 'application/x-msdos-program', 'application/x-exe',
            'application/javascript', 'text/javascript', 'application/x-javascript',
            'text/html', 'application/xhtml+xml', 'text/x-asp', 'application/x-asp',
            'application/x-java-archive', 'application/java-archive',
        ];

        foreach ($dangerous as $prefix) {
            if (str_starts_with((string) $mime, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
