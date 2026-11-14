<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Support\Html\SafeHtml;

/**
 * ViewModel for the site log page (log/index.blade.php).
 *
 * One template serves four modes — `dailylog`, `chronicle`, `news`,
 * `poll` — mode-specific props keep their defaults outside their mode.
 */
final class LogPageViewModel extends ViewModel
{
    /**
     * @param  list<array{id: string, label: string, url: string}>  $logTabs
     * @param  list<array<string, mixed>>  $logRows
     * @param  list<array<string, mixed>>  $chronicleRows
     * @param  array<string, mixed>|null  $editItem
     * @param  list<array<string, mixed>>  $newsRows
     * @param  list<array<string, mixed>>  $pollData
     */
    public function __construct(
        public readonly array $logTabs,
        public readonly string $mode,
        public readonly ?string $title,
        public readonly string $q = '',
        public readonly string $search = '',
        public readonly SafeHtml|string $pagerbottom = '',
        public readonly array $logRows = [],
        public readonly bool $canConfidentialLog = false,
        public readonly array $chronicleRows = [],
        public readonly ?array $editItem = null,
        public readonly bool $canManage = false,
        public readonly array $newsRows = [],
        public readonly array $pollData = [],
        public readonly bool $canPollManage = false,
    ) {}

    /**
     * Build from the controller's raw page-data array, ignoring keys the
     * template does not read.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip([
            'logTabs', 'mode', 'title', 'q', 'search', 'pagerbottom',
            'logRows', 'canConfidentialLog', 'chronicleRows', 'editItem', 'canManage',
            'newsRows', 'pollData', 'canPollManage',
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
