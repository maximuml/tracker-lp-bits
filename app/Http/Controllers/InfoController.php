<?php

namespace App\Http\Controllers;

use App\Enums\Permission\PermissionEnum;
use App\Models\Attendance;
use App\Models\News;
use App\Repositories\AttendanceRepository;
use App\Repositories\InfoRepository;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\Events;
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
use App\Support\Permissions;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

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
            $data = array_merge($data, InfoRepository::getUserHistoryPosts($userid, (int) ($curUser['class'] ?? 0), $perpage, $phpSelf));
        } elseif ($action === 'viewcomments') {
            $data = array_merge($data, InfoRepository::getUserHistoryComments($userid, $perpage, $phpSelf));
        }

        return $this->legacyPage($request, 'userhistory', true, $data);
    }

    public function invite(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'invite');
    }

    public function news(Request $request): Response|RedirectResponse|View
    {
        $langNews = (array) (SupportContext::getGlobal('lang_news') ?? []);
        $baseUrl = (string) SupportContext::getGlobal('BASEURL', '');

        $action = htmlspecialchars((string) ($request->input('action') ?? ''));

        if ($action === 'delete') {
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $returnto = $request->input('returnto') !== null && $request->input('returnto') !== ''
                ? htmlspecialchars((string) $request->input('returnto'))
                : htmlspecialchars((string) $request->headers->get('referer', ''));

            if ((int) $request->input('sure', 0) !== 1) {
                $confirm = ($langNews['std_are_you_sure'] ?? 'Are you sure? ') . "<a class=altlink href=\"?action=delete&newsid={$newsid}&returnto=" . urlencode($returnto) . "&sure=1\">" . ($langNews['std_here'] ?? 'here') . "</a>" . ($langNews['std_if_sure'] ?? '.');
                return $this->legacyAbortResponse($langNews['std_delete_news_item'] ?? 'Delete news item', $confirm, false);
            }

            News::query()->where('id', $newsid)->delete();
            $cache = SupportContext::getCache();
            if ($cache !== null) {
                $cache->delete_value('recent_news', true);
            }

            if ($returnto !== '') {
                return redirect($returnto);
            }

            return redirect('/');
        }

        if ($action === 'add') {
            if (! $request->isMethod('post')) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $body = htmlspecialchars((string) $request->input('body'), ENT_QUOTES);
            if ($body === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
            }
            $title = htmlspecialchars((string) $request->input('subject'));
            if ($title === '') {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
            }
            $added = (int) $request->input('added', 0);
            if ($added <= 0) {
                $added = now()->toDateTimeString();
            }
            $notify = $request->input('notify') === 'yes' ? 'yes' : 'no';

            $currentUser = (array) (SupportContext::getUser() ?? []);
            $newsId = (int) News::query()->insertGetId([
                'userid' => (int) ($currentUser['id'] ?? 0),
                'added' => $added,
                'body' => $body,
                'title' => $title,
                'notify' => $notify,
            ]);

            if (! $newsId) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_something_weird_happened'] ?? 'Something weird happened.');
            }

            $cache = SupportContext::getCache();
            if ($cache !== null) {
                $cache->delete_value('recent_news', true);
            }

            Events::fire('news_created', News::query()->find($newsId), null);

            return redirect('/');
        }

        if ($action === 'edit') {
            $newsid = (int) $request->input('newsid', 0);
            if ($newsid <= 0) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] ?? 'Invalid news ID.');
            }

            $news = News::query()->where('id', $newsid)->first();
            if (! $news) {
                return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_invalid_news_id'] . $newsid);
            }

            if ($request->isMethod('post')) {
                $body = htmlspecialchars((string) $request->input('body'), ENT_QUOTES);
                if ($body === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_body_empty'] ?? 'News body empty.');
                }
                $title = htmlspecialchars((string) $request->input('subject'));
                if ($title === '') {
                    return $this->legacyAbortResponse($langNews['std_error'] ?? 'Error', $langNews['std_news_title_empty'] ?? 'News title empty.');
                }
                $notify = $request->input('notify') === 'yes' ? 'yes' : 'no';

                News::query()->where('id', $newsid)->update([
                    'body' => $body,
                    'title' => $title,
                    'notify' => $notify,
                ]);

                $cache = SupportContext::getCache();
                if ($cache !== null) {
                    $cache->delete_value('recent_news', true);
                }

                return redirect('/');
            }

            $arr = $news->toArray();
            $newsTitle = $langNews['text_edit_site_news'] ?? 'Edit site news';
            $returnto = htmlspecialchars((string) ($request->input('returnto') ?? $request->headers->get('referer', '')));

            return $this->legacyPageRaw($request, 'news', true, [
                'mode' => 'edit',
                'newsid' => $newsid,
                'body' => $arr['body'] ?? '',
                'subject' => htmlspecialchars((string) ($arr['title'] ?? '')),
                'notify' => (string) ($arr['notify'] ?? 'no'),
                'returnto' => $returnto,
                'title' => $newsTitle,
            ]);
        }

        // Default: show compose form
        $composeTitle = $langNews['text_submit_news_item'] ?? 'Submit news item';
        return $this->legacyPageRaw($request, 'news', true, [
            'mode' => 'add',
            'title' => $composeTitle,
        ]);
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

        $faqData = InfoRepository::faqManageData();

        return $this->legacyPage($request, 'faqmanage', true, $faqData);
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
            InfoRepository::reorderFaq((array) SupportContext::getPost('order'));
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'edititem' && $request->isMethod('post')) {
            InfoRepository::updateFaq((int) SupportContext::getPost('id'), [
                'question' => (string) SupportContext::getPost('question'),
                'answer' => (string) SupportContext::getPost('answer'),
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => (int) SupportContext::getPost('categ'),
            ]);
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'editsect' && $request->isMethod('post')) {
            InfoRepository::updateFaq((int) SupportContext::getPost('id'), [
                'question' => (string) SupportContext::getPost('title'),
                'answer' => '',
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => 0,
            ]);
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'delete') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if (SupportContext::getQuery('confirm') === 'yes') {
                InfoRepository::deleteFaq($id);
                return redirect($redirectBase . '/faqmanage.php');
            }
            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'confirm_delete',
                'id' => $id,
            ]);
        }

        if ($action === 'addnewitem' && $request->isMethod('post')) {
            $categ = (int) (SupportContext::getPost('categ') ?? 0);
            $langId = (int) (SupportContext::getPost('langid') ?? 0);
            $max = InfoRepository::getFaqMaxOrderAndLinkId('item', $langId);
            InfoRepository::insertFaq([
                'link_id' => $max['maxlinkid'] + 1,
                'type' => 'item',
                'lang_id' => $langId,
                'question' => (string) SupportContext::getPost('question'),
                'answer' => (string) SupportContext::getPost('answer'),
                'flag' => (int) (SupportContext::getPost('flag') ?? 0),
                'categ' => $categ,
                'order' => $max['maxorder'] + 1,
            ]);
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'addnewsect' && $request->isMethod('post')) {
            $language = (int) (SupportContext::getPost('language') ?? 0);
            $max = InfoRepository::getFaqMaxOrderAndLinkId('categ', $language);
            InfoRepository::insertFaq([
                'link_id' => $max['maxlinkid'] + 1,
                'type' => 'categ',
                'lang_id' => $language,
                'question' => (string) SupportContext::getPost('title'),
                'answer' => '',
                'flag' => (int) (SupportContext::getPost('flag') ?? 0),
                'categ' => 0,
                'order' => $max['maxorder'] + 1,
            ]);
            return redirect($redirectBase . '/faqmanage.php');
        }

        if ($action === 'edit') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            $arr = InfoRepository::getFaqById($id);
            if ($arr === null) {
                return $this->legacyAbortResponse('Error', 'Invalid id');
            }
            $arr['question'] = htmlspecialchars((string) $arr['question']);
            $arr['answer'] = htmlspecialchars((string) $arr['answer']);

            $categories = [];
            if ($arr['type'] === 'item') {
                $categories = InfoRepository::getFaqCategoriesByLang((int) $arr['lang_id']);
            } elseif ($arr['type'] === 'categ') {
                $arr['lang_name'] = InfoRepository::getLanguageName((int) $arr['lang_id']);
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
