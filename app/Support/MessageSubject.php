<?php

declare(strict_types=1);

namespace App\Support;

final class MessageSubject
{
    public static function reply(string $subject): string
    {
        if (preg_match('/^Re:\s/', $subject)) {
            return preg_replace('/^Re:\s(.*)$/', 'Re(2): \\1', $subject) ?? $subject;
        }

        if (preg_match('/^Re\([0-9]*\):\s/', $subject)) {
            $replyCount = (int) preg_replace('/^Re\(([0-9]*)\):\s/', '\\1', $subject);
            $replyCount++;

            return preg_replace('/^Re\(([0-9]*)\):\s(.*)$/', 'Re('.$replyCount.'): \\2', $subject) ?? $subject;
        }

        return 'Re: '.$subject;
    }
}
