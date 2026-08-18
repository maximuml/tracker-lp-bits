<?php

declare(strict_types=1);

namespace App\DTOs\Usercp;

use Illuminate\Http\Request;

/**
 * Immutable DTO for forum display settings on the user control panel.
 */
final readonly class ForumSettingsDto
{
    public function __construct(
        public int $topicsperpage,
        public int $postsperpage,
        public string $avatars,
        public string $signatures,
        public string $clicktopic,
        public string $signature,
        public ?string $showlastpost,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $topicsperpage = max(0, min(100, (int) $request->input('topicsperpage', 0)));
        $postsperpage = max(0, min(100, (int) $request->input('postsperpage', 0)));
        $avatars = $request->input('avatars') === 'yes' ? 'yes' : 'no';
        $signatures = $request->input('signatures') === 'yes' ? 'yes' : 'no';

        $clicktopicRaw = (string) $request->input('clicktopic', '');
        $clicktopic = in_array($clicktopicRaw, ['firstpage', 'lastpage'], true) ? $clicktopicRaw : '';

        $signature = htmlspecialchars(trim((string) $request->input('signature', '')));

        $showlastpost = $request->has('ttlastpost')
            ? ($request->input('ttlastpost') === 'yes' ? 'yes' : 'no')
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
