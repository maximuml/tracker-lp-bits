<?php

declare(strict_types=1);

namespace App\ViewModels\Forum;

/**
 * One overforum section on the forums index: a visible group name plus
 * the forums the current user is allowed to read (ADR 0021).
 */
final class OverforumGroup
{
    /**
     * @param  list<ForumRow>  $forums
     */
    public function __construct(
        public readonly string $name,
        public readonly array $forums,
    ) {}
}
