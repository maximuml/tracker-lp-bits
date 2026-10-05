<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Services\CommentService;
use App\Support\Html\SafeHtml;
use App\Support\Pagination;
use App\Support\Smilies;
use App\ViewModels\Comment\CommentTableFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Torrent comment block on details.php: the paginated comment table plus
 * the quick-reply box. Posting runs through the same CommentService
 * pipeline as comment.php so flood control, owner PMs and bonuses behave
 * identically. Page navigation stays on full-reload pager links emitted
 * by the controller; this component only tracks the resolved page so a
 * post lands the reader on the last page.
 */
final class CommentSection extends Component
{
    private const PER_PAGE = 10;

    public int $parentId = 0;

    public int $page = -1;

    public bool $enabled = true;

    public string $text = '';

    public string $status = '';

    private ?CommentService $comments = null;

    private ?CommentTableFactory $factory = null;

    private ?TorrentDetailRepository $detailRepository = null;

    public function boot(CommentService $comments, CommentTableFactory $factory, TorrentDetailRepository $detailRepository): void
    {
        $this->comments = $comments;
        $this->factory = $factory;
        $this->detailRepository = $detailRepository;
    }

    public function mount(int $parentId, bool $enabled = true): void
    {
        $this->parentId = $parentId;
        $this->enabled = $enabled;
        $this->page = request()->query->getInt('page', -1);
    }

    public function post(): void
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return;
        }

        $body = trim($this->text);
        if ($body === '') {
            $this->status = (string) __('legacy/comment.std_comment_body_empty');

            return;
        }

        try {
            $this->comments()->post($user, 'torrent', $this->parentId, $body);
        } catch (HttpExceptionInterface $e) {
            $this->status = $e->getMessage() !== '' ? $e->getMessage() : (string) __('legacy/comment.std_permission_denied');

            return;
        }

        $this->reset('text');
        $this->status = '';
        $this->page = -1;
    }

    public function render(): View
    {
        $count = $this->detailRepository()->getCommentCount($this->parentId);
        $page = Pagination::resolvePage($this->page < 0 ? null : $this->page, $count, self::PER_PAGE, true);
        $rows = $this->enabled && $count > 0
            ? $this->detailRepository()->getComments($this->parentId, $page * self::PER_PAGE, self::PER_PAGE)
            : [];

        return view('livewire.comment-section', [
            'vm' => $this->factory()->build(
                array_values(array_map(fn ($comment) => (array) $comment, $rows)),
                'torrent',
                $this->parentId
            ),
            // Smilie quick-row emits anchors bound to document.forms['comment'].body
            // via SmileIT — same trusted markup path the classic quick reply used.
            'smileRow' => SafeHtml::fromTrustedHtml(Smilies::quickRow('comment', 'body')),
        ]);
    }

    private function comments(): CommentService
    {
        return $this->comments ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function factory(): CommentTableFactory
    {
        return $this->factory ?? throw new \LogicException('CommentSection used before boot()');
    }

    private function detailRepository(): TorrentDetailRepository
    {
        return $this->detailRepository ?? throw new \LogicException('CommentSection used before boot()');
    }
}
