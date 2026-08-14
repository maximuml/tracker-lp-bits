<?php

namespace App\Http\Controllers;

use App\Support\Frame;
use App\Support\Html;
use App\Support\Http;
use App\Support\Locale;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        return $this->legacyPage($request, 'userhistory');
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

    public function attendance(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'attendance');
    }

    public function aboutNexus(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'aboutnexus', false);
    }

    public function rules(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'rules', false);
    }

    public function userAgreement(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'useragreement', false);
    }

    public function faq(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'faq', false);
    }

    public function donate(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'donate', false);
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
