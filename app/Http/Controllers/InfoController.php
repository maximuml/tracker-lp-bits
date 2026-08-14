<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Attendance;
use App\Models\User;
use App\Repositories\AttendanceRepository;
use App\Repositories\InfoRepository;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\LegacyAuthContext;
use App\Support\Category;
use App\Support\Format;
use App\Support\Frame;
use App\Support\Html;
use App\Support\Http;
use App\Support\LegacyResponse;
use App\Support\SearchBox;
use App\Support\Locale;
use Carbon\Carbon;
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
        if ($request->isMethod('post')) {
            return $this->handleGetrssPost($request);
        }

        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            return redirect('/getrss.php');
        }

        return $this->legacyPage($request, 'getrss', true, $this->getrssData());
    }

    /**
     * @return array<string, mixed>
     */
    private function getrssData(): array
    {
        $browsecatmode = (int) (SupportContext::getGlobal('browsecatmode') ?? 1);
        $brsectiontype = $browsecatmode;

        $showsubcat = (bool) SearchBox::valueWithContext($brsectiontype, 'showsubcat');
        $showsource = (bool) SearchBox::valueWithContext($brsectiontype, 'showsource');
        $showmedium = (bool) SearchBox::valueWithContext($brsectiontype, 'showmedium');
        $showcodec = (bool) SearchBox::valueWithContext($brsectiontype, 'showcodec');
        $showstandard = (bool) SearchBox::valueWithContext($brsectiontype, 'showstandard');
        $showprocessing = (bool) SearchBox::valueWithContext($brsectiontype, 'showprocessing');
        $showaudiocodec = (bool) SearchBox::valueWithContext($brsectiontype, 'showaudiocodec');
        $catsperrow = (int) SearchBox::valueWithContext($brsectiontype, 'catsperrow');
        $catpadding = SearchBox::valueWithContext($brsectiontype, 'catpadding');

        $brcats = Category::listByModeWithContext($brsectiontype);

        $data = compact(
            'browsecatmode',
            'brsectiontype',
            'showsubcat',
            'showsource',
            'showmedium',
            'showcodec',
            'showstandard',
            'showprocessing',
            'showaudiocodec',
            'catsperrow',
            'catpadding',
            'brcats'
        );

        if ($showsubcat) {
            if ($showsource) {
                $data['sources'] = SearchBox::itemListWithContext('sources', $brsectiontype);
            }
            if ($showmedium) {
                $data['media'] = SearchBox::itemListWithContext('media', $brsectiontype);
            }
            if ($showcodec) {
                $data['codecs'] = SearchBox::itemListWithContext('codecs', $brsectiontype);
            }
            if ($showstandard) {
                $data['standards'] = SearchBox::itemListWithContext('standards', $brsectiontype);
            }
            if ($showprocessing) {
                $data['processings'] = SearchBox::itemListWithContext('processings', $brsectiontype);
            }
            if ($showaudiocodec) {
                $data['audiocodecs'] = SearchBox::itemListWithContext('audiocodecs', $brsectiontype);
            }
        }

        $data['categories'] = SearchBox::buildCategoryTableWithContext($browsecatmode, 'yes', 'torrents.php?allsec=1&', '', 3, '', ['section_name' => true]);
        $data['allowed_showrows'] = ['10', '50'];
        $data['stickyTypes'] = [
            0 => Locale::trans('torrent.pos_state_normal', [], null),
            1 => Locale::trans('torrent.pos_state_sticky', [], null),
            2 => Locale::trans('torrent.pos_state_r_sticky', [], null),
        ];

        return $data;
    }

    private function handleGetrssPost(Request $request): Response|RedirectResponse
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            return redirect('/getrss.php');
        }

        $lang_getrss = (array) (SupportContext::getGlobal('lang_getrss') ?? []);
        $browsecatmode = (int) (SupportContext::getGlobal('browsecatmode') ?? 1);
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        $allowedShowrows = ['10', '50'];
        $showrows = (string) $request->input('showrows', '10');
        if (! in_array($showrows, $allowedShowrows, true)) {
            return $this->getrssMessageResponse($lang_getrss['std_error'] ?? 'Error', $lang_getrss['std_no_row'] ?? 'No row');
        }

        $query = ['passkey' => $curUser['passkey'] ?? '', 'rows' => (int) $showrows];

        $brcats = Category::listByModeWithContext($browsecatmode);
        foreach ($brcats as $cat) {
            if ($request->filled('cat' . $cat['id'])) {
                $query['cat' . $cat['id']] = 1;
            }
        }

        if (SearchBox::valueWithContext($browsecatmode, 'showsubcat')) {
            $subcatMap = [
                'showsource' => 'sources',
                'showmedium' => 'media',
                'showcodec' => 'codecs',
                'showstandard' => 'standards',
                'showprocessing' => 'processings',
                'showaudiocodec' => 'audiocodecs',
            ];
            foreach ($subcatMap as $flag => $table) {
                if (! SearchBox::valueWithContext($browsecatmode, $flag)) {
                    continue;
                }
                $subcatKeyMap = [
                    'sources' => 'sou',
                    'media' => 'med',
                    'codecs' => 'cod',
                    'standards' => 'sta',
                    'processings' => 'pro',
                    'audiocodecs' => 'aud',
                ];
                $key = $subcatKeyMap[$table];
                foreach (SearchBox::itemListWithContext($table, $browsecatmode) as $item) {
                    if ($request->filled($key . $item['id'])) {
                        $query[$key . $item['id']] = 1;
                    }
                }
            }
        }

        if ($request->filled('itemcategory')) {
            $query['icat'] = 1;
        }
        if ($request->filled('itemsmalldescr')) {
            $query['ismalldescr'] = 1;
        }
        if ($request->filled('itemsize')) {
            $query['isize'] = 1;
        }
        if ($request->filled('itemuploader')) {
            $query['iuplder'] = 1;
        }

        $searchstr = trim((string) $request->input('search', ''));
        if ($searchstr !== '') {
            $query['search'] = rawurlencode($searchstr);
            if ($request->filled('search_mode')) {
                $searchMode = (int) $request->input('search_mode');
                if (! in_array($searchMode, [0, 2], true)) {
                    $searchMode = 0;
                }
                $query['search_mode'] = $searchMode;
            }
        }

        $sticky = $request->input('sticky');
        if (is_array($sticky) && ! empty($sticky)) {
            $query['sticky'] = implode(',', array_map('intval', $sticky));
        }

        if ($request->filled('paid')) {
            $query['paid'] = (int) $request->input('paid');
        }

        $inclbookmarked = (int) $request->input('inclbookmarked', 0);
        $addinclbm = '';
        if (in_array($inclbookmarked, [0, 1], true)) {
            $addinclbm = '&inclbookmarked=' . $inclbookmarked;
        }

        $link = Http::protocolPrefix(Url::isSecure()) . $baseUrl . '/torrentrss.php?' . http_build_query($query) . $addinclbm;
        $msg = ($lang_getrss['std_use_following_url'] ?? 'Use the following URL:') . "\n" . $link . "\n\n"
            . ($lang_getrss['std_utorrent_feed_url'] ?? 'uTorrent feed URL:') . "\n" . $link . '&linktype=dl' . $addinclbm;

        return $this->getrssMessageResponse($lang_getrss['std_done'] ?? 'Done', Format::formatComment($msg), $lang_getrss['head_rss_feeds'] ?? 'RSS Feeds');
    }

    private function getrssMessageResponse(string $heading, string $text, string $title = ''): Response
    {
        ob_start();
        Html::stdhead($title);
        Html::stdMessage($heading, $text);
        Html::stdfoot();

        return response((string) ob_get_clean());
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

    public function news(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'news');
    }

    public function makepoll(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'makepoll');
    }

    public function polloverview(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'polloverview');
    }

    public function attendance(Request $request, AttendanceRepository $repository): View|RedirectResponse|Response
    {
        $curUser = SupportContext::getUser();
        if ($curUser === null) {
            return redirect('/attendance.php');
        }

        $uid = (int) ($curUser['id'] ?? 0);
        $captchaEnabled = SiteConfig::current()->captcha->attendanceEnabled((bool) config('captcha.attendance.enabled', true));

        if ($request->isMethod('post')) {
            if ($captchaEnabled && SupportContext::getGlobal('iv', '') === 'yes') {
                Captcha::checkCode(
                    (string) (SupportContext::getPost('imagehash') ?? ''),
                    (string) (SupportContext::getPost('imagestring') ?? ''),
                    'attendance.php',
                    false,
                    true
                );
            }
            $attendance = $repository->attend($uid);
            $langAttendance = (array) (SupportContext::getGlobal('lang_attendance') ?? []);
            if (! $attendance->is_updated) {
                LegacyResponse::abort($langAttendance['sorry'] ?? '', $langAttendance['already_attended'] ?? '');
            }
        } else {
            $attendance = $repository->getAttendance($uid);
            if (! $captchaEnabled && ! ($attendance && $attendance->added && $attendance->added->isSameDay(Carbon::today()))) {
                $attendance = $repository->attend($uid);
            }
        }

        if (! $attendance) {
            $attendance = new Attendance([
                'uid' => $uid,
                'points' => 0,
                'days' => 0,
                'total_days' => 0,
            ]);
        }

        $data = $repository->buildViewData($attendance, $uid);
        $data['attendanceCaptchaEnabled'] = $captchaEnabled;

        return $this->legacyPage($request, 'attendance', true, $data);
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
