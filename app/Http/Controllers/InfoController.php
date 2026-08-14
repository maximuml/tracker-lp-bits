<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\InfoRepository;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class InfoController extends LegacyController
{
    public function getrss(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'getrss');
    }

    public function userhistory(Request $request): View|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            $qs = $request->getQueryString();
            return redirect('/userhistory.php' . ($qs ? '?' . $qs : ''));
        }

        $userid = (int) SupportContext::getQuery('id');
        LegacyResponse::assertId($userid, true);

        $viewerId = (int) ($curUser['id'] ?? 0);
        if ($viewerId != $userid && ! Permissions::userCan(PermissionEnum::VIEW_USER_HISTORY->value, false, $viewerId)) {
            LegacyResponse::permissionDenied();
        }

        $action = htmlspecialchars((string) SupportContext::getQuery('action'));
        $perpage = 15;
        $phpSelf = SupportContext::getServerValue('PHP_SELF');
        $subject = \App\Support\UserDisplay::username($userid);

        $data = [
            'action' => $action,
            'userid' => $userid,
            'subject' => $subject,
        ];

        if ($action === 'viewposts') {
            $postcount = (int) NexusDB::table('posts as p')
                ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
                ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
                ->where('p.userid', $userid)
                ->where('f.minclassread', '<=', (int) $curUser['class'])
                ->distinct()
                ->count('p.id');

            [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $postcount, $phpSelf . "?action=viewposts&id=$userid&");

            $posts = NexusDB::table('posts as p')
                ->leftJoin('topics as t', 'p.topicid', '=', 't.id')
                ->leftJoin('forums as f', 't.forumid', '=', 'f.id')
                ->leftJoin('readposts as r', function ($join) {
                    $join->on('p.topicid', '=', 'r.topicid')->on('p.userid', '=', 'r.userid');
                })
                ->where('p.userid', $userid)
                ->where('f.minclassread', '<=', (int) $curUser['class'])
                ->orderByDesc('p.id')
                ->offset($offset)
                ->limit($perpage)
                ->get(['f.id AS f_id', 'f.name', 't.id AS t_id', 't.subject', 't.lastpost', 'r.lastpostread', 'p.*'])
                ->map(fn ($row) => (array) $row)
                ->toArray();

            $editorIds = array_filter(array_unique(array_column($posts, 'editedby')));
            $editorNames = ! empty($editorIds)
                ? User::query()->whereIn('id', $editorIds)->pluck('username', 'id')->toArray()
                : [];

            $data['postcount'] = $postcount;
            $data['pagertop'] = $pagertop;
            $data['pagerbottom'] = $pagerbottom;
            $data['perpage'] = $perpage;
            $data['posts'] = $posts;
            $data['editorNames'] = $editorNames;
        } elseif ($action === 'viewcomments') {
            $commentcount = (int) NexusDB::table('comments as c')
                ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
                ->where('c.user', $userid)
                ->count();

            [$pagertop, $pagerbottom, , $offset, $perpage] = Pagination::pager($perpage, $commentcount, $phpSelf . "?action=viewcomments&id=$userid&");

            $comments = NexusDB::table('comments as c')
                ->leftJoin('torrents as t', 'c.torrent', '=', 't.id')
                ->where('c.user', $userid)
                ->orderByDesc('c.id')
                ->offset($offset)
                ->limit($perpage)
                ->get(['t.name', 'c.torrent AS t_id', 'c.id', 'c.added', 'c.text'])
                ->map(fn ($row) => (array) $row)
                ->toArray();

            $countsBefore = [];
            foreach ($comments as $comment) {
                $countsBefore[$comment['id']] = (int) NexusDB::table('comments')
                    ->where('torrent', $comment['t_id'])
                    ->where('id', '<', $comment['id'])
                    ->count();
            }

            $data['commentcount'] = $commentcount;
            $data['pagertop'] = $pagertop;
            $data['pagerbottom'] = $pagerbottom;
            $data['perpage'] = $perpage;
            $data['comments'] = $comments;
            $data['commentPageMap'] = $countsBefore;
        }

        return $this->legacyPage($request, 'userhistory', true, $data);
    }

    public function invite(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'invite');
    }

    public function news(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'news');
    }

    public function makepoll(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'makepoll');
    }

    public function polloverview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'polloverview');
    }

    public function attendance(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'attendance');
    }

    public function aboutNexus(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'aboutnexus', false, InfoRepository::aboutNexus());
    }

    public function rules(Request $request): Response|RedirectResponse
    {
        $langFolder = (string) SupportContext::getGlobal('CURLANGDIR', 'en');
        $cacheKey = "{$langFolder}_rules";

        $html = Cache::remember($cacheKey, 900, function () {
            $langId = InfoRepository::resolveRuleLangId(Locale::guestIdWithContext());

            return view('rules.index', ['rules' => InfoRepository::rules($langId)])->render();
        });

        return response($html);
    }

    public function userAgreement(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'useragreement', false);
    }

    public function faq(Request $request): Response|RedirectResponse
    {
        $langFolder = (string) SupportContext::getGlobal('CURLANGDIR', 'en');
        $cacheKey = "{$langFolder}_faq";

        $html = Cache::remember($cacheKey, 900, function () {
            $langId = InfoRepository::resolveRuleLangId(Locale::guestIdWithContext());

            return view('faq.index', ['faqCategories' => InfoRepository::faqCategories($langId)])->render();
        });

        return response($html);
    }

    public function donate(Request $request): View|RedirectResponse
    {
        $data = InfoRepository::donationPageData();
        $data['thanks'] = $request->query('do') === 'thanks';

        return $this->legacyPage($request, 'donate', false, $data);
    }

    public function donated(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'donated');
    }

    public function faqManage(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'faqmanage');
    }

    public function faqActions(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'faqactions');
    }

    public function bitbucketlog(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'bitbucketlog', true);
    }
}
