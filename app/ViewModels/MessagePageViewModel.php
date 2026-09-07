<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the messages page.
 *
 * Returned by MessagePageService::build().
 */
final class MessagePageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $lang
     * @param  array<string, mixed>  $curUser
     * @param  array<string, mixed>|null  $viewmessage
     * @param  array<string, mixed>|null  $forward
     * @param  array<string, mixed>|null  $editmailboxes
     * @param  array<string, mixed>|null  $viewmailbox
     */
    public function __construct(
        public readonly array $lang,
        public readonly array $curUser,
        public readonly int $userId,
        public readonly string $action,
        public readonly string $baseUrl,
        public readonly string $contentWidth,
        public readonly ?array $viewmessage = null,
        public readonly ?array $forward = null,
        public readonly ?array $editmailboxes = null,
        public readonly ?array $viewmailbox = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'lang' => $this->lang,
            'curUser' => $this->curUser,
            'userId' => $this->userId,
            'action' => $this->action,
            'baseUrl' => $this->baseUrl,
            'contentWidth' => $this->contentWidth,
            'viewmessage' => $this->viewmessage,
            'forward' => $this->forward,
            'editmailboxes' => $this->editmailboxes,
            'viewmailbox' => $this->viewmailbox,
        ];
    }
}
