<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Models\Comment;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\CommentRepository;
use App\Support\Bonus;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Locale;
use App\Support\Permissions;
use App\Support\Url;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class WebCommentController extends Controller
{
    public function create(Request $request): View
    {
        $type = $this->type($request);
        $parentId = $this->parentId($request);

        $this->authorizeComment($type, $parentId);

        $parent = app(CommentRepository::class)->getParent($parentId, $type);
        if (! $parent) {
            abort(404, $this->lang('std_no_torrent_id'));
        }

        $composeType = 'reply';
        $body = '';
        if ($request->input('sub') === 'quote') {
            $commentId = (int) $request->input('cid', 0);
            if ($commentId <= 0) {
                abort(404, $this->lang('std_no_comment_id'));
            }
            $quote = app(CommentRepository::class)->getQuote($commentId);
            if (! $quote) {
                abort(404, $this->lang('std_no_comment_id'));
            }
            $body = $this->buildQuote($quote);
            $composeType = 'quote';
        }

        $returnUrl = $this->buildScript($type, $parentId);
        $headTitle = $this->lang('head_add_comment_to').$parent['name'];
        $pageTitle = $this->lang('text_add_comment_to').'<a href="'.e($returnUrl).'">'.e($parent['name']).'</a>';
        $formAction = $this->legacyAction('add', ['type' => $type]);

        return view('comments.create', compact('headTitle', 'pageTitle', 'formAction', 'composeType', 'body', 'parentId'));
    }

    public function store(StoreCommentRequest $request): RedirectResponse
    {
        $type = $request->validated('type');
        $parentId = (int) $request->validated('pid');
        $body = trim($request->validated('body'));

        if ($body === '') {
            abort(403, $this->lang('std_comment_body_empty'));
        }

        $user = $this->currentUser();

        $this->authorizeComment($type, $parentId);
        $this->assertNotFlood($user);

        $parent = app(CommentRepository::class)->getParent($parentId, $type);
        if (! $parent) {
            abort(404, $this->lang('std_no_torrent_id'));
        }

        $newId = app(CommentRepository::class)->create($parentId, $type, $body, (int) $user->id);
        $this->deleteCache($type, $parentId);
        $this->sendCommentPm($type, $parentId, (int) $parent['owner'], (string) $parent['name'], (int) $user->id);
        $this->applyBonus('+', (int) $user->id);

        return redirect($this->buildScript($type, $parentId).'#'.$newId);
    }

    public function edit(Request $request, int $commentId): View
    {
        $type = $this->type($request);

        $arr = app(CommentRepository::class)->getForEdit($commentId, $type);
        if (! $arr) {
            abort(404, $this->lang('std_invalid_id'));
        }

        $user = $this->currentUser();
        if ((int) $arr['user'] !== (int) $user->id && ! Permissions::userCan('commanage', false, (int) $user->id)) {
            abort(403, $this->lang('std_permission_denied'));
        }

        $parentId = (int) $arr['parent_id'];
        $returnUrl = $this->buildScript($type, $parentId);
        $headTitle = $this->lang('head_edit_comment_to').$arr['name'];
        $pageTitle = $this->lang('text_edit_comment_to').'<a href="'.e($returnUrl).'">'.e($arr['name']).'</a>';
        $formAction = $this->legacyAction('edit', ['type' => $type, 'cid' => $commentId]);
        $returnto = $this->safeReturnUrl((string) ($request->headers->get('referer') ?? ''), $returnUrl);
        $body = (string) $arr['text'];
        $composeType = 'edit';

        return view('comments.edit', compact('headTitle', 'pageTitle', 'formAction', 'returnto', 'composeType', 'body'));
    }

    public function update(UpdateCommentRequest $request, int $commentId): RedirectResponse
    {
        $type = $request->validated('type');
        $body = trim($request->validated('body'));

        if ($body === '') {
            abort(403, $this->lang('std_comment_body_empty'));
        }

        $user = $this->currentUser();

        $arr = app(CommentRepository::class)->getForEdit($commentId, $type);
        if (! $arr) {
            abort(404, $this->lang('std_invalid_id'));
        }
        if ((int) $arr['user'] !== (int) $user->id && ! Permissions::userCan('commanage', false, (int) $user->id)) {
            abort(403, $this->lang('std_permission_denied'));
        }

        app(CommentRepository::class)->update($commentId, $body, (int) $user->id);
        $this->deleteCache($type, (int) $arr['parent_id']);

        $defaultUrl = $this->buildScript($type, (int) $arr['parent_id']);
        $returnto = $request->validated('returnto', '');

        return redirect($this->safeReturnUrl($returnto, $defaultUrl));
    }

    public function deleteConfirm(Request $request, int $commentId): View
    {
        $type = $this->type($request);

        $user = $this->currentUser();
        if (! Permissions::userCan('commanage', false, (int) $user->id)) {
            abort(403, $this->lang('std_permission_denied'));
        }

        $referer = (string) ($request->headers->get('referer') ?? '');
        $query = ['type' => $type];
        if ($referer !== '') {
            $query['returnto'] = $referer;
        }
        $formAction = $this->legacyAction('delete', ['type' => $type]);
        $heading = $this->lang('std_delete_comment');
        $message = $this->lang('std_delete_comment_note');
        $confirmLabel = ltrim($this->lang('std_here_if_sure'), '>');
        $cancelLabel = $this->lang('text_cancel');
        $cancelUrl = $referer !== '' ? $referer : $this->buildScript($type, 0);
        $returnto = $referer;

        return view('comments.delete', compact('heading', 'message', 'formAction', 'commentId', 'confirmLabel', 'cancelLabel', 'cancelUrl', 'type', 'returnto'));
    }

    public function destroy(Request $request, int $commentId): RedirectResponse
    {
        $type = $this->type($request);

        $user = $this->currentUser();
        if (! Permissions::userCan('commanage', false, (int) $user->id)) {
            abort(403, $this->lang('std_permission_denied'));
        }

        $arr = app(CommentRepository::class)->getForDelete($commentId, $type);
        if (! $arr) {
            abort(404, $this->lang('std_invalid_id'));
        }

        $parentId = (int) $arr['pid'];
        $userPostId = (int) $arr['user'];

        if (app(CommentRepository::class)->delete($commentId, $type, $parentId)) {
            $this->deleteCache($type, $parentId);
        }
        $this->applyBonus('-', $userPostId);

        $defaultUrl = $this->buildScript($type, $parentId);
        $returnto = (string) $request->input('returnto', '');
        if ($returnto === '') {
            $returnto = (string) ($request->headers->get('referer') ?? '');
        }

        return redirect($this->safeReturnUrl($returnto, $defaultUrl));
    }

    public function original(Request $request, int $commentId): View
    {
        $type = $this->type($request);

        $user = $this->currentUser();
        if (! Permissions::userCan('commanage', false, (int) $user->id)) {
            abort(403, $this->lang('std_permission_denied'));
        }

        $arr = app(CommentRepository::class)->getForViewOriginal($commentId, $type);
        if (! $arr) {
            abort(404, $this->lang('std_invalid_id'));
        }

        $parentId = (int) ($arr[$type] ?? 0);
        $defaultUrl = $this->buildScript($type, $parentId);
        $returnto = $this->safeReturnUrl((string) ($request->headers->get('referer') ?? ''), $defaultUrl);
        $lang = $this->langComment();

        return view('comments.original', compact('arr', 'commentId', 'returnto', 'lang'));
    }

    private function currentUser(): User
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            abort(403, $this->lang('std_permission_denied'));
        }

        return $user;
    }

    private function type(Request $request): string
    {
        $type = $request->input('type');
        if (! in_array($type, ['torrent', 'offer'], true)) {
            abort(404, $this->lang('std_unknown_action'));
        }

        return (string) $type;
    }

    private function parentId(Request $request): int
    {
        $pid = (int) $request->input('pid', 0);
        if ($pid <= 0) {
            abort(404, $this->lang('std_no_torrent_id'));
        }

        return $pid;
    }

    private function authorizeComment(string $type, int $parentId): void
    {
        $user = $this->currentUser();
        if ($user->parked) {
            abort(403, $this->lang('std_permission_denied'));
        }

        if ($type === 'torrent') {
            $torrent = Torrent::find($parentId);
            if (! $torrent) {
                abort(404, $this->lang('std_no_torrent_id'));
            }
            Gate::authorize('comment', $torrent);
        }
    }

    private function assertNotFlood(User $user): void
    {
        if (Permissions::userCan('commanage', false, (int) $user->id)) {
            return;
        }

        $lastComment = $user->last_comment;
        if ($lastComment === null || $lastComment === '') {
            return;
        }

        $ts = strtotime((string) $lastComment);
        if ($ts === false || $ts <= (TIMENOW - 10)) {
            return;
        }

        $secs = 10 - (TIMENOW - $ts);
        abort(403, $this->lang('std_comment_flooding_denied').$secs.$this->lang('std_before_posting_another'));
    }

    /** @param array<int|string, mixed> $quote */
    private function buildQuote(array $quote): string
    {
        return '[quote='.(string) ($quote['username'] ?? '').']'.(string) ($quote['text'] ?? '').'[/quote]';
    }

    private function deleteCache(string $type, int $parentId): void
    {
        Cache::forgetWithLocales($type.'_'.$parentId.'_last_comment_content');
    }

    private function applyBonus(string $sign, int $userId): void
    {
        $points = SiteConfig::current()->bonus->addComment();
        if ($points != 0) {
            Bonus::updatePoints($sign, $points, $userId);
        }
    }

    private function sendCommentPm(string $type, int $parentId, int $ownerId, string $name, int $commenterId): void
    {
        if ($ownerId === $commenterId) {
            return;
        }

        if (! app(CommentRepository::class)->getCommentPmSetting($ownerId)) {
            return;
        }

        $locale = Locale::userLocale($ownerId);
        $subject = Locale::trans('comment.msg_new_comment', [], $locale);
        $messageKey = 'comment.msg_'.$type.'_receive_comment';
        $message = Locale::trans($messageKey, [], $locale)
            .' [url='.Http::protocolPrefix(Url::isSecure()).rtrim((string) SiteConfig::current()->basic->baseUrl(), '/').'/'.$this->buildScript($type, $parentId).'] '.$name.'[/url].';

        Message::add([
            'sender' => null,
            'receiver' => $ownerId,
            'subject' => $subject,
            'added' => now(),
            'msg' => $message,
        ]);
    }

    private function buildScript(string $type, int $parentId): string
    {
        $script = Comment::TYPE_MAPS[$type]['target_script'] ?? 'details.php?id=%s';

        return sprintf($script, $parentId);
    }

    /** @param array<string, mixed> $query */
    private function legacyAction(string $action, array $query): string
    {
        $query = ['action' => $action] + $query;

        return 'comment.php?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function safeReturnUrl(string $returl, string $defaultUrl): string
    {
        $returl = trim($returl);
        if ($returl === '') {
            return $defaultUrl;
        }

        $parsed = parse_url($returl);
        if ($parsed === false || ! empty($parsed['scheme']) || ! empty($parsed['host']) || str_starts_with($returl, '//')) {
            return $defaultUrl;
        }

        return $returl;
    }

    /** @return array<string, string> */
    private function langComment(): array
    {
        return (array) app(Globals::class)->get('lang_comment', []);
    }

    private function lang(string $key): string
    {
        $lang = $this->langComment();

        return (string) ($lang[$key] ?? '');
    }
}
