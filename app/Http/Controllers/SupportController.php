<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Setting;
use App\Models\User;
use App\Services\ComplainService;
use App\Support\Captcha;
use App\Support\CurrentUser;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupportController extends LegacyController
{
    public function __construct(
        private readonly ComplainService $complainService,
        private readonly CurrentUser $currentUser,
    ) {}

    public function complains(Request $request): View|RedirectResponse|Response
    {
        $currentUser = (array) ($this->currentUser->get() ?? []);
        $uid = (int) ($currentUser['id'] ?? 0);
        $isAdmin = Permission::can(PermissionEnum::STAFF_MEMBER);

        if ($uid > 0 && ! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }
        if (! $isAdmin && ! Setting::getIsComplainEnabled()) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), __('legacy/complains.complain_not_enabled'));
        }

        if ($request->isMethod('post')) {
            return $this->handleComplainPost($request, $uid, $isAdmin);
        }

        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($action) && $isAdmin) {
            $action = 'list';
        }

        return match ($action) {
            'list' => $this->complainList($request, $isAdmin),
            'view' => $this->complainView($request, $uid, $isAdmin),
            default => $this->complainCompose($request, $uid),
        };
    }

    private function handleComplainPost(Request $request, int $uid, bool $isAdmin): RedirectResponse|Response
    {
        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        return match ($action) {
            'new' => $this->complainNew($request),
            'reply' => $this->complainReply($request, $uid),
            'answered', 'unanswered' => $this->complainToggle($request, $isAdmin, $action),
            default => $this->legacyAbortResponse(__('legacy/functions.std_error'), 'Permission denied.'),
        };
    }

    private function complainNew(Request $request): RedirectResponse|Response
    {
        if ((int) ($this->currentUser->get()['id'] ?? 0) === 0) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), 'Permission denied.');
        }

        if (! Captcha::checkCode(
            (string) ($request->input('imagehash') ?? ''),
            (string) ($request->input('imagestring') ?? ''),
            'complains.php',
            false,
            true,
        )) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), __('legacy/complains.text_new_failure'));
        }

        $email = filter_var((string) ($request->input('email') ?? ''), FILTER_VALIDATE_EMAIL);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($email) || empty($body)) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), __('legacy/complains.text_new_failure'));
        }

        $uuid = $this->complainService->createComplain($email, $body, Network::clientIp());
        if ($uuid === null) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), __('legacy/complains.text_new_failure'));
        }

        return redirect('/complains.php?action=view&id='.urlencode($uuid));
    }

    private function complainReply(Request $request, int $uid): RedirectResponse|Response
    {
        $id = (int) $request->input('id', 0);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($id <= 0 || empty($body)) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), __('legacy/complains.text_new_failure'));
        }

        $ok = $this->complainService->replyToComplain($id, $uid, $body, Network::clientIp());
        if (! $ok) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), 'Complain not found.');
        }

        return redirect()->to($request->headers->get('referer') ?: '/complains.php');
    }

    private function complainToggle(Request $request, bool $isAdmin, string $action): RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $this->complainService->toggleAnswered($id, $action === 'answered');

        return redirect()->to($request->headers->get('referer') ?: '/complains.php');
    }

    private function complainList(Request $request, bool $isAdmin): View|RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $pendingRows = [];
        if ($request->input('page') === null) {
            $pendingRows = DB::table('complains')
                ->where('answered', 0)
                ->orderByDesc('id')
                ->get(['added', 'uuid', 'email'])
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        $count = (int) DB::table('complains')->where('answered', 1)->count();
        [$pagertop, $pagerbottom, , $offset, $rpp] = Pagination::pager(20, $count, '?action=list&');
        $processedRows = DB::table('complains')
            ->where('answered', 1)
            ->orderByDesc('id')
            ->offset($offset)
            ->limit($rpp)
            ->get(['added', 'uuid', 'email'])
            ->map(fn ($r) => (array) $r)
            ->all();

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'list',
            'pendingRows' => $pendingRows,
            'processedRows' => $processedRows,
            'count' => $count,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'page' => $request->input('page'),
            'title' => __('legacy/complains.text_complain'),
            'isAdmin' => $isAdmin,
            'isLogin' => true,
        ]);
    }

    private function complainView(Request $request, int $uid, bool $isAdmin): View|RedirectResponse|Response
    {
        $uuid = filter_var((string) ($request->input('id') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (strlen($uuid) !== 36) {
            return $this->legacyAbortResponse(('Error'), 'Permission denied.');
        }

        $complain = (array) DB::table('complains')->where('uuid', $uuid)->first();
        if (empty($complain)) {
            return $this->legacyAbortResponse(('Error'), 'Complain not found.');
        }

        $user = User::query()->where('email', (string) ($complain['email'] ?? ''))->first(['id', 'username']);

        $replyRows = DB::table('complain_replies')
            ->where('complain', (int) ($complain['id'] ?? 0))
            ->orderByDesc('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $replyUserIds = array_filter(array_unique(array_column($replyRows, 'userid')));
        $replyUserMap = [];
        foreach ($replyUserIds as $rUid) {
            $replyUserMap[(int) $rUid] = UserDisplay::plainUsername((int) $rUid);
        }

        $replyBoxHtml = Html::quickReply('reply', 'body', __('legacy/complains.text_reply'));

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'view',
            'complain' => $complain,
            'user' => $user?->toArray(),
            'replyRows' => $replyRows,
            'replyUserMap' => $replyUserMap,
            'isAdmin' => $isAdmin,
            'isLogin' => $uid > 0,
            'title' => __('legacy/complains.text_complain'),
            'replyBoxHtml' => SafeHtml::fromTrustedHtml($replyBoxHtml),
        ]);
    }

    private function complainCompose(Request $request, int $uid): View|RedirectResponse|Response
    {
        if ($uid <= 0) {
            return $this->legacyAbortResponse(__('legacy/functions.std_error'), 'Permission denied.');
        }

        ob_start();
        Captcha::showImageCode('grid');
        $captchaHtml = (string) ob_get_clean();

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'compose',
            'title' => __('legacy/complains.text_complain'),
            'captchaHtml' => SafeHtml::fromTrustedHtml($captchaHtml),
        ]);
    }
}
