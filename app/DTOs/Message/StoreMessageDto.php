<?php

declare(strict_types=1);

namespace App\DTOs\Message;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Immutable DTO for sending a private message.
 */
final readonly class StoreMessageDto
{
    /**
     * @param  int  $sender  Authenticated user id.
     */
    public function __construct(
        public int $receiver,
        public string $subject,
        public string $msg,
        public int $sender,
        public string $added,
        public bool $unread = true,
        public int $location = 1,
        public bool $saved = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'receiver' => 'required|integer|exists:users,id',
            'subject' => 'required|string|max:255',
            'msg' => 'required|string',
        ]);

        return new self(
            (int) $validated['receiver'],
            (string) $validated['subject'],
            (string) $validated['msg'],
            (int) Auth::id(),
            now()->toDateTimeString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'receiver' => $this->receiver,
            'subject' => $this->subject,
            'msg' => $this->msg,
            'sender' => $this->sender,
            'added' => $this->added,
            'unread' => $this->unread,
            'location' => $this->location,
            'saved' => $this->saved,
        ];
    }
}
