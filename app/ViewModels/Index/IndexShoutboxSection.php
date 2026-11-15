<?php

declare(strict_types=1);

namespace App\ViewModels\Index;

/**
 * Shoutbox iframe + compose form section on the index page.
 */
final readonly class IndexShoutboxSection
{
    public function __construct(
        public bool $show = false,
        public string $title = '',
        public string $autoRefreshLabel = '',
        public string $secondsLabel = '',
        public string $historyLabel = '',
        public bool $canManage = false,
        public string $clearLabel = '',
        public string $clearConfirm = '',
        public string $messageLabel = '',
        public string $submitLabel = '',
        public string $clearButtonLabel = '',
        public string $showHideTitle = '',
        public int $refreshSeconds = 0,
    ) {}
}
