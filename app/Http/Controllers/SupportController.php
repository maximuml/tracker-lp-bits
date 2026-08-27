<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\Complain;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\ToolRepository;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Logger;
use App\Support\Network;
use App\Support\Pagination;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Nexus\Database\NexusLock;

class SupportController extends LegacyController
{
    private ToolRepository $toolRepository;

    public function __construct(ToolRepository $toolRepository)
    {
        $this->toolRepository = $toolRepository;
    }

    public function complains(Request $request): View|RedirectResponse|Response
    {
        $currentUser = (array) (app(CurrentUser::class)->get() ?? []);
        $uid = (int) ($currentUser['id'] ?? 0);
        $isAdmin = Permission::can(PermissionEnum::STAFF_MEMBER);
        $langComplains = (array) (app(Globals::class)->get('lang_complains') ?? []);
        $langFunctions = (array) (app(Globals::class)->get('lang_functions') ?? []);

        if ($uid > 0 && ! $isAdmin) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Permission denied.');
        }
        if (! $isAdmin && ! Setting::getIsComplainEnabled()) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['complain_not_enabled'] ?? 'Complains are not enabled.');
        }

        if ($request->isMethod('post')) {
            return $this->handleComplainPost($request, $uid, $isAdmin, $langComplains, $langFunctions);
        }

        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($action) && $isAdmin) {
            $action = 'list';
        }

        return match ($action) {
            'list' => $this->complainList($request, $isAdmin, $langComplains),
            'view' => $this->complainView($request, $uid, $isAdmin, $langComplains, $langFunctions),
            default => $this->complainCompose($request, $uid, $langComplains, $langFunctions),
        };
    }

    /**
     * @param  array<string, mixed>  $langComplains
     * @param  array<string, mixed>  $langFunctions
     */
    private function handleComplainPost(Request $request, int $uid, bool $isAdmin, array $langComplains, array $langFunctions): RedirectResponse|Response
    {
        $action = filter_var((string) ($request->input('action') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        return match ($action) {
            'new' => $this->complainNew($request, $langComplains, $langFunctions),
            'reply' => $this->complainReply($request, $uid, $langComplains, $langFunctions),
            'answered', 'unanswered' => $this->complainToggle($request, $isAdmin, $action, $langComplains),
            default => $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', 'Permission denied.'),
        };
    }

    /**
     * @param  array<string, mixed>  $langComplains
     * @param  array<string, mixed>  $langFunctions
     */
    private function complainNew(Request $request, array $langComplains, array $langFunctions): RedirectResponse|Response
    {
        if ((int) (app(CurrentUser::class)->get()['id'] ?? 0) === 0) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', 'Permission denied.');
        }

        if (! Captcha::checkCode(
            (string) ($request->input('imagehash') ?? ''),
            (string) ($request->input('imagestring') ?? ''),
            'complains.php',
            false,
            true,
        )) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Invalid captcha.');
        }

        try {
            NexusLock::lockOrFail('complains:lock:'.Network::clientIp(), 10);
        } catch (\Throwable $e) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Unable to process request.');
        }

        $email = filter_var((string) ($request->input('email') ?? ''), FILTER_VALIDATE_EMAIL);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (empty($email) || empty($body)) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Missing data.');
        }

        try {
            NexusLock::lockOrFail('complains:lock:'.$email, 600);
        } catch (\Throwable $e) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Unable to process request.');
        }

        $user = User::query()->where('email', $email)->where('enabled', 'no')->first();
        if (! $user) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Unable to find disabled account.');
        }

        $complainId = (int) DB::table('complains')->insertGetId([
            'uuid' => DB::raw('UUID()'),
            'email' => $email,
            'body' => $body,
            'added' => now()->toDateTimeString(),
            'ip' => Network::clientIp(),
        ]);

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('COMPLAINTS_COUNT_CACHE');
        }

        $uuid = (string) Complain::query()->where('id', $complainId)->value('uuid');

        return redirect('/complains.php?action=view&id='.urlencode($uuid));
    }

    /**
     * @param  array<string, mixed>  $langComplains
     * @param  array<string, mixed>  $langFunctions
     */
    private function complainReply(Request $request, int $uid, array $langComplains, array $langFunctions): RedirectResponse|Response
    {
        $id = (int) $request->input('id', 0);
        $body = filter_var((string) ($request->input('body') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ($id <= 0 || empty($body)) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', $langComplains['text_new_failure'] ?? 'Missing data.');
        }

        $complain = Complain::query()->find($id);
        if (! $complain) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', 'Complain not found.');
        }

        DB::table('complain_replies')->insert([
            'complain' => $id,
            'userid' => $uid,
            'added' => now()->toDateTimeString(),
            'body' => $body,
            'ip' => Network::clientIp(),
        ]);

        if ($uid > 0) {
            try {
                $toolRep = $this->toolRepository;
                $toolRep->sendMail(
                    $complain->email,
                    $langComplains['reply_notify_subject'] ?? 'Reply to your complain',
                    sprintf(
                        $langComplains['reply_notify_body'] ?? '',
                        SiteConfig::current()->basic->siteName(),
                        Url::schemeAndHost(false).'/complains.php?action=view&id='.$complain->uuid
                    )
                );
            } catch (\Throwable $exception) {
                Logger::writeWithContext((string) $exception->getMessage(), 'error', false);
            }
        }

        return redirect()->to($request->headers->get('referer') ?: '/complains.php');
    }

    /**
     * @param  array<string, mixed>  $langComplains
     */
    private function complainToggle(Request $request, bool $isAdmin, string $action, array $langComplains): RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Permission denied.');
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Permission denied.');
        }

        DB::table('complains')->where('id', $id)->update([
            'answered' => $action === 'answered' ? 1 : 0,
        ]);

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('COMPLAINTS_COUNT_CACHE');
        }

        return redirect()->to($request->headers->get('referer') ?: '/complains.php');
    }

    /**
     * @param  array<string, mixed>  $langComplains
     */
    private function complainList(Request $request, bool $isAdmin, array $langComplains): View|RedirectResponse|Response
    {
        if (! $isAdmin) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Permission denied.');
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
            'title' => $langComplains['text_complain'] ?? 'Complain',
        ]);
    }

    /**
     * @param  array<string, mixed>  $langComplains
     * @param  array<string, mixed>  $langFunctions
     */
    private function complainView(Request $request, int $uid, bool $isAdmin, array $langComplains, array $langFunctions): View|RedirectResponse|Response
    {
        $uuid = filter_var((string) ($request->input('id') ?? ''), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if (strlen($uuid) !== 36) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Permission denied.');
        }

        $complain = (array) DB::table('complains')->where('uuid', $uuid)->first();
        if (empty($complain)) {
            return $this->legacyAbortResponse($langComplains['std_error'] ?? 'Error', 'Complain not found.');
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

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'view',
            'complain' => $complain,
            'user' => $user?->toArray(),
            'replyRows' => $replyRows,
            'replyUserMap' => $replyUserMap,
            'isAdmin' => $isAdmin,
            'isLogin' => $uid > 0,
            'title' => $langComplains['text_complain'] ?? 'Complain',
        ]);
    }

    /**
     * @param  array<string, mixed>  $langComplains
     * @param  array<string, mixed>  $langFunctions
     */
    private function complainCompose(Request $request, int $uid, array $langComplains, array $langFunctions): View|RedirectResponse|Response
    {
        if ($uid <= 0) {
            return $this->legacyAbortResponse($langFunctions['std_error'] ?? 'Error', 'Permission denied.');
        }

        return $this->legacyPage($request, 'complains', false, [
            'mode' => 'compose',
            'title' => $langComplains['text_complain'] ?? 'Complain',
        ]);
    }
}
