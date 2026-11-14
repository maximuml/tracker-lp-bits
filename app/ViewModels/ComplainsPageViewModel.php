<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the complains page (complains/index.blade.php).
 *
 * Assembled by SupportController; covers list, view and compose modes; dead keys pagertop/pagerbottom dropped.
 */
final class ComplainsPageViewModel extends ViewModel
{
    /**
     * @param  array<int, array<string, mixed>>  $pendingRows
     * @param  array<int, array<string, mixed>>  $processedRows
     * @param  array<string, mixed>  $pager
     * @param  array<string, mixed>  $complain
     * @param  array<string, mixed>|null  $user
     * @param  array<int, array<string, mixed>>  $replyRows
     * @param  array<int, string>  $replyUserMap
     */
    public function __construct(
        public readonly string $mode,
        public readonly string|SafeHtml $title,
        public readonly array $pendingRows = [],
        public readonly array $processedRows = [],
        public readonly int $count = 0,
        public readonly array $pager = [],
        public readonly string|int|null $page = null,
        public readonly bool $isAdmin = false,
        public readonly bool $isLogin = false,
        public readonly array $complain = [],
        public readonly ?array $user = null,
        public readonly array $replyRows = [],
        public readonly array $replyUserMap = [],
        public readonly SafeHtml|string $replyBoxHtml = '',
        public readonly SafeHtml|string $captchaHtml = '',
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['mode', 'pendingRows', 'processedRows', 'count', 'pager', 'page', 'title', 'isAdmin', 'isLogin', 'complain', 'user', 'replyRows', 'replyUserMap', 'replyBoxHtml', 'captchaHtml'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
