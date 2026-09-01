<?php

declare(strict_types=1);

/**
 * @property int $id
 * @property int $userid
 * @property int $width
 * @property string|null $added
 * @property string $filename
 * @property string $dlkey
 * @property string $filetype
 * @property int $filesize
 * @property string $location
 * @property int $downloads
 * @property int $isimage
 * @property int $thumb
 * @property string $driver
 */

namespace App\Models;

class Attachment extends NexusModel
{
    const IMG_EXTENSIONS = ['jpeg', 'jpg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff', 'heic'];

    /** @var list<string> */
    protected $fillable = ['id', 'userid', 'width', 'added', 'filename', 'dlkey', 'filetype', 'filesize', 'location', 'downloads', 'isimage', 'thumb', 'driver'];

    /** @var array<string, string> */
    protected $casts = [
        'added' => 'datetime',
        'width' => 'integer',
        'filesize' => 'integer',
        'downloads' => 'integer',
        'isimage' => 'boolean',
        'thumb' => 'boolean',
    ];
}
