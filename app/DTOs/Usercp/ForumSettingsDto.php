<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use App\Enums\UserClickTopic;
use Illuminate\Http\Request;

/**
 * Immutable DTO for forum display settings on the user control panel.
 */
final readonly class ForumSettingsDto
{
    public function __construct(
        public int $topicsperpage,
        public int $postsperpage,
        public bool $avatars,
        public bool $signatures,
        public ?int $clicktopic,
        public string $signature,
        public ?bool $showlastpost,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $topicsperpage = max(0, min(100, (int) $request->input('topicsperpage', 0)));
        $postsperpage = max(0, min(100, (int) $request->input('postsperpage', 0)));
        $avatars = $request->input('avatars') === 'yes';
        $signatures = $request->input('signatures') === 'yes';

        $clicktopicRaw = $request->input('clicktopic', '');
        if (is_int($clicktopicRaw) || (is_string($clicktopicRaw) && ctype_digit($clicktopicRaw))) {
            $clicktopic = (int) $clicktopicRaw;
        } elseif (is_string($clicktopicRaw) && $clicktopicRaw !== '') {
            $clicktopic = UserClickTopic::fromStringSafe($clicktopicRaw)->value;
        } else {
            $clicktopic = null;
        }

        $signature = htmlspecialchars(trim((string) $request->input('signature', '')));

        $showlastpost = $request->has('ttlastpost')
            ? $request->input('ttlastpost') === 'yes'
            : null;

        return new self(
            $topicsperpage,
            $postsperpage,
            $avatars,
            $signatures,
            $clicktopic,
            $signature,
            $showlastpost,
        );
    }
}
