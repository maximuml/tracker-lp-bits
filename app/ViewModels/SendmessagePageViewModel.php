<?php

declare(strict_types=1);

namespace App\ViewModels;

/**
 * ViewModel for the sendmessage page (sendmessage/index.blade.php).
 *
 * Assembled by MessageController::sendmessage(); dead key stdheadMsgalert dropped.
 */
final class SendmessagePageViewModel extends ViewModel
{
    public function __construct(
        public readonly mixed $receiver = null,
        public readonly mixed $replyto = null,
        public readonly mixed $subject = null,
        public readonly mixed $body = null,
        public readonly mixed $returnto = null,
        public readonly mixed $title = null,
        public readonly mixed $frameTitle = null,
        public readonly mixed $deleteChecked = null,
        public readonly mixed $saveChecked = null,
    ) {}

    /**
     * Build from a raw page-data array, keeping only the keys the view reads.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip(['receiver', 'replyto', 'subject', 'body', 'returnto', 'title', 'frameTitle', 'deleteChecked', 'saveChecked'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
