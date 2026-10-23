<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\DTOs\Auth\ActorContext;
use App\Models\Comment;
use App\Repositories\TorrentDetailRepository;
use App\Services\CommentService;
use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use App\Support\Smilies;
use App\ViewModels\Comment\CommentTableFactory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Comment block shared by details.php (type "torrent") and offers.php
 * (type "offer"): the paginated comment table plus the quick-reply box.
 * Posting runs through the same CommentService pipeline as comment.php
 * so flood control, owner PMs and bonuses behave identically. Page
 * navigation stays on full-reload pager links emitted by the controller;
 * this component only tracks the resolved page so a post lands the
 * reader on the last page.
 */
final class CommentSection extends Component
{
    private const PER_PAGE = 10;

    #[Locked]
    public int $parentId = 0;

    #[Locked]
    public string $type = 'torrent';

    public int $page = -1;

    #[Locked]
    public bool $enabled = true;

    public string $text = '';

    public string $status = '';

    private ?CommentService $comments = null;

    private ?CommentTableFactory $factory = null;

    private ?TorrentDetailRepository $detailRepository = null;

    private ?OfferCommentRepositoryInterface $offerRepository = null;

    private ?ActorContext $actor = null;

    public function boot(
        CommentService $comments,
        CommentTableFactory $factory,
        TorrentDetailRepository $detailRepository,
        OfferCommentRepositoryInterface $offerRepository,
        ActorContext $actor,
    ): void {
        $this->comments = $comments;
        $this->factory = $factory;
        $this->detailRepository = $detailRepository;
        $this->offerRepository = $offerRepository;
        $this->actor = $actor;
    }

    public function mount(int $parentId, string $type = 'torrent', bool $enabled = true): void
    {
        $this->parentId = $parentId;
        $this->type = $type;
        $this->enabled = $enabled;
        $this->page = request()->query->getInt('page', -1);
    }

    public function post(): void
    {
        $user = $this->actor()->user;
        if ($user === null) {
            return;
        }

        $body = trim($this->text);
        if ($body === '') {
            $this->status = (string) __('comment.std_comment_body_empty');

            return;
        }

        try {
            $this->comments()->post($user, $this->type, $this->parentId, $body);
        } catch (HttpExceptionInterface $e) {
            $this->status = $e->getMessage() !== '' ? $e->getMessage() : (string) __('comment.std_permission_denied');

            return;
        }

        $this->reset('text');
        $this->status = '';
        $this->page = -1;
    }

    public function render(): View
    {
        [, $rows] = $this->commentsForPage();

        return view('livewire.comment-section', [
            'vm' => $this->factory()->build($rows, $this->type, $this->parentId),
            // Smilie quick-row emits anchors bound to document.forms['comment'].body
            // via SmileIT — same trusted markup path the classic quick reply used.
            'smileRow' => SafeHtml::fromTrustedHtml(Smilies::quickRow('comment', 'body')),
        ]);
    }

    /**
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function commentsForPage(): array
    {
        if ($this->type === 'offer') {
            $count = $this->offerRepository()->countComments($this->parentId);
            $page = Pagination::resolvePage($this->page < 0 ? null : $this->page, $count, self::PER_PAGE, true);
            $rows = $this->enabled && $count > 0
                ? array_values(array_map(
                    fn (Comment $comment) => $comment->toArray(),
                    $this->offerRepository()->getComments($this->parentId, $page * self::PER_PAGE, self::PER_PAGE)->all()
                ))
                : [];

            return [$count, $rows];
        }

        $count = $this->detailRepository()->getCommentCount($this->parentId);
        $page = Pagination::resolvePage($this->page < 0 ? null : $this->page, $count, self::PER_PAGE, true);
        $rows = $this->enabled && $count > 0
            ? array_values(array_map(
                fn (Comment $comment) => $comment->toArray(),
                $this->detailRepository()->getComments($this->parentId, $page * self::PER_PAGE, self::PER_PAGE)->all()
            ))
            : [];

        return [$count, $rows];
    }

    private function comments(): CommentService
    {
        return $this->comments ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function factory(): CommentTableFactory
    {
        return $this->factory ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function actor(): ActorContext
    {
        return $this->actor ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function detailRepository(): TorrentDetailRepository
    {
        return $this->detailRepository ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function offerRepository(): OfferCommentRepositoryInterface
    {
        return $this->offerRepository ?? throw new \LogicException('CommentSection used before boot()');
    }
}
