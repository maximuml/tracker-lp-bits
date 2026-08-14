<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\User;
use App\Repositories\InfoRepository;
use App\Support\Frame;
use App\Support\Html;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Nexus\Database\NexusDB;

class InfoController extends LegacyController
{
    public function getrss(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'getrss');
    }

    public function userhistory(Request $request): View|RedirectResponse|Response
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

    public function invite(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'invite');
    }

    public function news(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'news');
    }

    public function makepoll(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'makepoll');
    }

    public function polloverview(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'polloverview');
    }

    public function attendance(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'attendance');
    }

    public function aboutNexus(Request $request): View|RedirectResponse|Response
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

    public function userAgreement(Request $request): View|RedirectResponse|Response
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

    public function donate(Request $request): View|RedirectResponse|Response
    {
        $data = InfoRepository::donationPageData();
        $data['thanks'] = $request->query('do') === 'thanks';

        return $this->legacyPage($request, 'donate', false, $data);
    }

    public function donated(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'donated');
    }

    public function faqManage(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $categRows = NexusDB::table('faq')
            ->leftJoin('language', 'faq.lang_id', '=', 'language.id')
            ->where('faq.type', 'categ')
            ->orderBy('language.lang_name')
            ->orderBy('faq.order')
            ->get(['faq.id', 'faq.link_id', 'faq.lang_id', 'language.lang_name', 'faq.question', 'faq.flag', 'faq.order']);

        $faqCateg = [];
        foreach ($categRows as $row) {
            $arr = (array) $row;
            $faqCateg[$arr['lang_id']][$arr['link_id']] = [
                'title' => $arr['question'],
                'flag' => $arr['flag'],
                'order' => $arr['order'],
                'id' => $arr['id'],
                'lang_name' => $arr['lang_name'],
                'items' => $faqCateg[$arr['lang_id']][$arr['link_id']]['items'] ?? [],
            ];
        }

        $itemRows = NexusDB::table('faq')
            ->where('type', 'item')
            ->orderBy('order')
            ->get(['id', 'question', 'lang_id', 'flag', 'categ', 'order']);

        foreach ($itemRows as $row) {
            $arr = (array) $row;
            $faqCateg[$arr['lang_id']][$arr['categ']]['items'][$arr['id']] = [
                'question' => $arr['question'],
                'flag' => $arr['flag'],
                'order' => $arr['order'],
            ];
        }

        $faqOrphaned = [];
        foreach ($faqCateg as $lang => $temp2) {
            foreach ($temp2 as $id => $temp) {
                if (! array_key_exists('title', $temp)) {
                    foreach ($temp['items'] as $id2 => $tempItem) {
                        $faqOrphaned[$lang][$id2] = $tempItem;
                    }
                    unset($faqCateg[$lang][$id]);
                }
            }
        }

        return $this->legacyPage($request, 'faqmanage', true, [
            'faqCateg' => $faqCateg,
            'faqOrphaned' => $faqOrphaned,
        ]);
    }

    public function faqActions(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the FAQ, sorry.');
        }

        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');
        $redirectBase = Http::protocolPrefix(Url::isSecure()) . $baseUrl;
        $action = (string) (SupportContext::getQuery('action') ?? '');

        if ($action === 'reorder' && $request->isMethod('post')) {
            $order = (array) SupportContext::getPost('order');
            foreach ($order as $id => $position) {
                NexusDB::table('faq')->where('id', (int) $id)->update(['order' => (int) $position]);
            }
            NexusDB::cache_del('faq');
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'edititem' && $request->isMethod('post')) {
            $question = (string) SupportContext::getPost('question');
            $answer = (string) SupportContext::getPost('answer');
            NexusDB::table('faq')->where('id', (int) SupportContext::getPost('id'))->update([
                'question' => $question,
                'answer' => $answer,
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => (int) SupportContext::getPost('categ'),
            ]);
            NexusDB::cache_del('faq');
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'editsect' && $request->isMethod('post')) {
            $title = (string) SupportContext::getPost('title');
            NexusDB::table('faq')->where('id', (int) SupportContext::getPost('id'))->update([
                'question' => $title,
                'answer' => '',
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => 0,
            ]);
            NexusDB::cache_del('faq');
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'delete') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if (SupportContext::getQuery('confirm') === 'yes') {
                NexusDB::table('faq')->where('id', $id)->delete();
                NexusDB::cache_del('faq');
                return redirect($redirectBase . '/faqmanage.php');
            }
            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'confirm_delete',
                'id' => $id,
            ]);
        }

        if ($action === 'addnewitem' && $request->isMethod('post')) {
            $question = (string) SupportContext::getPost('question');
            $answer = (string) SupportContext::getPost('answer');
            $categ = (int) (SupportContext::getPost('categ') ?? 0);
            $langId = (int) (SupportContext::getPost('langid') ?? 0);
            $maxRow = (array) NexusDB::table('faq')
                ->where('type', 'item')
                ->where('categ', $categ)
                ->where('lang_id', $langId)
                ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
                ->first();
            $order = ($maxRow['maxorder'] ?? 0) + 1;
            $linkId = ($maxRow['maxlinkid'] ?? 0) + 1;
            NexusDB::table('faq')->insert([
                'link_id' => $linkId,
                'type' => 'item',
                'lang_id' => $langId,
                'question' => $question,
                'answer' => $answer,
                'flag' => (int) (SupportContext::getPost('flag') ?? 0),
                'categ' => $categ,
                'order' => $order,
            ]);
            NexusDB::cache_del('faq');
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'addnewsect' && $request->isMethod('post')) {
            $title = (string) SupportContext::getPost('title');
            $language = (int) (SupportContext::getPost('language') ?? 0);
            $maxRow = (array) NexusDB::table('faq')
                ->where('type', 'categ')
                ->where('lang_id', $language)
                ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
                ->first();
            $order = ($maxRow['maxorder'] ?? 0) + 1;
            $linkId = ($maxRow['maxlinkid'] ?? 0) + 1;
            NexusDB::table('faq')->insert([
                'link_id' => $linkId,
                'type' => 'categ',
                'lang_id' => $language,
                'question' => $title,
                'answer' => '',
                'flag' => (int) (SupportContext::getPost('flag') ?? 0),
                'categ' => 0,
                'order' => $order,
            ]);
            NexusDB::cache_del('faq');
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'edit') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $arr = (array) NexusDB::table('faq')->where('id', $id)->first();
            if (empty($arr)) {
                return $this->legacyAbortResponse('Error', 'Invalid id');
            }
            $arr['question'] = htmlspecialchars((string) $arr['question']);
            $arr['answer'] = htmlspecialchars((string) $arr['answer']);

            $categories = [];
            if ($arr['type'] === 'item') {
                $categories = NexusDB::table('faq')
                    ->where('type', 'categ')
                    ->where('lang_id', $arr['lang_id'])
                    ->orderBy('order')
                    ->get(['id', 'question', 'link_id'])
                    ->map(fn ($r) => (array) $r)
                    ->all();
            } elseif ($arr['type'] === 'categ') {
                $arr['lang_name'] = NexusDB::table('language')->where('id', $arr['lang_id'])->value('lang_name') ?? '';
            }

            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'edit',
                'arr' => $arr,
                'categories' => $categories,
            ]);
        }

        if ($action === 'additem') {
            $inId = (int) (SupportContext::getQuery('inid') ?? 0);
            $langId = (int) (SupportContext::getQuery('langid') ?? 0);
            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'additem',
                'inid' => $inId,
                'langid' => $langId,
            ]);
        }

        if ($action === 'addsection') {
            $languages = Locale::languageList('rule_lang', null);
            $defLang = SupportContext::getGlobal('deflang', '');
            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'addsection',
                'languages' => $languages,
                'deflang' => $defLang,
            ]);
        }

        return redirect($redirectBase . '/faqmanage.php');
    }

    public function bitbucketlog(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'bitbucketlog', true);
    }
}
