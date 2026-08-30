<?php

declare(strict_types=1);

namespace App\DTOs\Message;

use Illuminate\Http\Request;

/**
 * Immutable DTO for a paginated mailbox query.
 */
final readonly class MessageListDto
{
    public function __construct(
        public int $userId,
        public int $mailbox,
        public ?bool $unread,
        public string $keyword,
        public string $place,
        public int $perPage,
        public int $page,
    ) {}

    public static function fromRequest(Request $request, int $userId): self
    {
        $mailbox = max(0, (int) $request->input('mailbox', 0));
        $rawUnread = $request->input('unread');
        $unread = match (true) {
            $rawUnread === true || $rawUnread === 'yes' || $rawUnread === '1' => true,
            $rawUnread === false || $rawUnread === 'no' || $rawUnread === '0' => false,
            default => null,
        };

        $keyword = (string) $request->input('keyword', '');
        $place = (string) $request->input('place', '');
        $perPage = max(1, min(100, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('page', 1));

        return new self($userId, $mailbox, $unread, $keyword, $place, $perPage, $page);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
