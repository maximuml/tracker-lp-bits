<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                ->leftJoin('readposts as r', function ($join) use ($userid) {
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

            $torrentIds = array_column($comments, 't_id');
            $commentIds = array_column($comments, 'id');
            $countsBefore = [];
            if (! empty($torrentIds) && ! empty($commentIds)) {
                $placeholdersTorrents = implode(',', array_fill(0, count($torrentIds), '?'));
                $placeholdersComments = implode(',', array_fill(0, count($commentIds), '?'));
                $rows = NexusDB::select(
                    "SELECT c.torrent, c.id, COUNT(c2.id) AS before_count
                     FROM comments c
                     LEFT JOIN comments c2 ON c2.torrent = c.torrent AND c2.id < c.id
                     WHERE c.torrent IN ($placeholdersTorrents) AND c.id IN ($placeholdersComments)
                     GROUP BY c.torrent, c.id",
                    array_merge($torrentIds, $commentIds)
                );
                foreach ($rows as $row) {
                    $countsBefore[$row->id] = (int) $row->before_count;
                }
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
        return $this->legacyPage($request, 'aboutnexus', false);
    }

    public function rules(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'rules', false);
    }

    public function userAgreement(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'useragreement', false);
    }

    public function faq(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'faq', false);
    }

    public function donate(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'donate', false);
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
