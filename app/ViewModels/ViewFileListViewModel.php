<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the viewfilelist AJAX fragment (viewfilelist/index.blade.php).
 *
 * Assembled by TorrentAjaxController::viewFileList().
 */
final class ViewFileListViewModel extends ViewModel
{
    /**
     * @param  list<ViewFileListRow>  $files
     */
    public function __construct(
        public readonly array $files,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
