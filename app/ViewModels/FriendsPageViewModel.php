<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the friends page (friends/index.blade.php).
 *
 * Assembled by FriendsController::friends().
 */
final class FriendsPageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $userid = null,
        public readonly mixed $friendsList = null,
        public readonly mixed $blocks = null,
        public readonly mixed $titleUsername = null,
        public readonly mixed $title = null,
        public readonly mixed $canViewUserList = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['userid', 'friendsList', 'blocks', 'titleUsername', 'title', 'canViewUserList'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
