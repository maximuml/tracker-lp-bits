<?php

namespace App\Http\Controllers;

use App\Repositories\InfoRepository;
use App\Support\Http;
use App\Support\Locale;
use App\Support\SupportContext;
use App\Support\Url;
use App\Support\UserDisplay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class FaqController extends LegacyController
{
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
        $redirectBase = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        $action = (string) (SupportContext::getQuery('action') ?? '');

        if ($action === 'reorder' && $request->isMethod('post')) {
            InfoRepository::reorderFaq((array) SupportContext::getPost('order'));

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'edititem' && $request->isMethod('post')) {
            InfoRepository::updateFaq((int) SupportContext::getPost('id'), [
                'question' => (string) SupportContext::getPost('question'),
                'answer' => (string) SupportContext::getPost('answer'),
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => (int) SupportContext::getPost('categ'),
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'editsect' && $request->isMethod('post')) {
            InfoRepository::updateFaq((int) SupportContext::getPost('id'), [
                'question' => (string) SupportContext::getPost('title'),
                'answer' => '',
                'flag' => (int) SupportContext::getPost('flag'),
                'categ' => 0,
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'delete') {
            $id = (int) (SupportContext::getQuery('id') ?? 0);
            if (SupportContext::getQuery('confirm') === 'yes') {
                InfoRepository::deleteFaq($id);

                return redirect($redirectBase.'/faqmanage.php');
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

            return redirect($redirectBase.'/faqmanage.php');
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

            return redirect($redirectBase.'/faqmanage.php');
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

        return redirect($redirectBase.'/faqmanage.php');
    }
}
