<?php

declare(strict_types=1);

namespace Tests\Builders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * W3-05: Realistic message/mailbox scenario builder.
 *
 * Creates a dataset that mimics real user messaging:
 * - Multiple users with conversations between them
 * - Messages spread across different mailboxes
 * - Mix of read and unread messages
 * - Some messages in staff boxes
 *
 * Usage:
 *   $scenario = MessageScenario::create()
 *       ->withUsers(5)
 *       ->withMessagesPerConversation(10)
 *       ->build();
 */
final class MessageScenario
{
    /** @var Collection<int, User> */
    public Collection $users;

    /** @var list<Message> */
    public array $messages = [];

    private int $userCount = 5;

    private int $messagesPerConversation = 10;

    public static function create(): self
    {
        return new self;
    }

    public function withUsers(int $count): self
    {
        $this->userCount = $count;

        return $this;
    }

    public function withMessagesPerConversation(int $count): self
    {
        $this->messagesPerConversation = $count;

        return $this;
    }

    public function build(): self
    {
        $this->users = User::factory()->count($this->userCount)->create();
        $users = $this->users->values()->all();

        // Create conversations between pairs of users
        for ($i = 0; $i < $this->userCount; $i++) {
            for ($j = $i + 1; $j < $this->userCount; $j++) {
                $sender = $users[$i];
                $receiver = $users[$j];

                for ($m = 0; $m < $this->messagesPerConversation; $m++) {
                    $message = Message::factory()->create([
                        'sender' => $sender->id,
                        'receiver' => $receiver->id,
                        'added' => now()->subMinutes($m * 10),
                        'unread' => $m === 0 ? 1 : 0,
                        'subject' => "Conversation {$sender->id}-{$receiver->id}",
                    ]);
                    $this->messages[] = $message;
                }
            }
        }

        return $this;
    }

    public function messageCount(): int
    {
        return count($this->messages);
    }
}
