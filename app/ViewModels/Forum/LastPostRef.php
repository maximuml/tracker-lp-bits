<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

use App\Support\Html\SafeHtml;

/**
 * Reference to the last post of a forum or topic row (ADR 0021).
 *
 * `subject` is the display title truncated to 35 chars by the service;
 * `fullSubject` goes into the link `title` attribute. `poster` is the
 * rich username markup produced by `UserDisplay::username()` — one of
 * the two SafeHtml fields allowed on forum VMs (the other is the
 * BBCode-rendered post body).
 */
final class LastPostRef
{
    public function __construct(
        public readonly int $topicId,
        public readonly string $subject,
        public readonly string $fullSubject,
        public readonly int $hlcolor,
        public readonly string $date,
        public readonly SafeHtml $poster,
    ) {}
}
