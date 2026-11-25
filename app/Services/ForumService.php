<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dispatches forum action endpoints. Post submission (new topic, reply,
 * edit) lives in {@see ForumPostingService}; moderation mutations (move,
 * delete, lock, sticky, highlight) in {@see ForumModerationService}.
 */
final class ForumService
{
    public function __construct(
        private readonly ForumModerationService $moderation,
        private readonly ForumPostingService $posting,
    ) {}

    /**
     * @return array<string, mixed>|RedirectResponse
     */
    public function legacy(Request $request): array|RedirectResponse
    {
        $action = (string) $request->input('action', $request->query('action', ''));

        if (! $request->isMethod('POST')) {
            return match ($action) {
                'deletetopic' => $this->moderation->deleteTopic($request),
                'deletepost' => $this->moderation->deletePost($request),
                default => [],
            };
        }

        if ($action === 'post') {
            return $this->posting->handlePost($request);
        }
        if ($action === 'movetopic') {
            return $this->moderation->moveTopic($request);
        }
        if ($action === 'deletetopic') {
            return $this->moderation->deleteTopic($request);
        }
        if ($action === 'deletepost') {
            return $this->moderation->deletePost($request);
        }
        if ($action === 'setlocked') {
            return $this->moderation->setLocked($request);
        }
        if ($action === 'hltopic') {
            return $this->moderation->highlightTopic($request);
        }
        if ($action === 'setsticky') {
            return $this->moderation->setSticky($request);
        }

        // Read-only actions are rendered by ForumPageService in the
        // controller. Signal "handled as read" with an empty array.
        return [];
    }

    public function post(Request $request): RedirectResponse
    {
        return $this->posting->handlePost($request);
    }

    public function moveTopic(Request $request): RedirectResponse
    {
        return $this->moderation->moveTopic($request);
    }

    public function deleteTopic(Request $request): RedirectResponse
    {
        return $this->moderation->deleteTopic($request);
    }

    public function deletePost(Request $request): RedirectResponse
    {
        return $this->moderation->deletePost($request);
    }

    public function setLocked(Request $request): RedirectResponse
    {
        return $this->moderation->setLocked($request);
    }

    public function highlightTopic(Request $request): RedirectResponse
    {
        return $this->moderation->highlightTopic($request);
    }

    public function setSticky(Request $request): RedirectResponse
    {
        return $this->moderation->setSticky($request);
    }
}
