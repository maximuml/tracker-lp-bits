<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\InfoRepository;
use App\Support\Globals;
use App\Support\Http;
use App\Support\Locale;
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
        $langFolder = (string) app(Globals::class)->get('CURLANGDIR', 'en');
        $cacheKey = "{$langFolder}_faq";

        $html = Cache::remember($cacheKey, 900, function () {
            $langId = app(InfoRepository::class)->resolveRuleLangId(Locale::guestIdWithContext());

            return view('faq.index', ['faqCategories' => app(InfoRepository::class)->faqCategories($langId)])->render();
        });

        return response($html);
    }

    public function faqManage(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $faqData = app(InfoRepository::class)->faqManageData();

        return $this->legacyPage($request, 'faqmanage', true, $faqData);
    }

    public function faqActions(Request $request): View|RedirectResponse|Response
    {
        $administratorClass = defined('UC_ADMINISTRATOR') ? \constant('UC_ADMINISTRATOR') : 0;
        if (UserDisplay::currentClass() < $administratorClass) {
            return $this->legacyAbortResponse('Error', 'Only Administrators and above can modify the FAQ, sorry.');
        }

        $baseUrl = (string) app(Globals::class)->get('BASEURL', '');
        $redirectBase = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        $action = (string) (request()->query('action') ?? '');

        if ($action === 'reorder' && $request->isMethod('post')) {
            app(InfoRepository::class)->reorderFaq((array) request()->post('order'));

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'edititem' && $request->isMethod('post')) {
            app(InfoRepository::class)->updateFaq((int) request()->post('id'), [
                'question' => (string) request()->post('question'),
                'answer' => (string) request()->post('answer'),
                'flag' => (int) request()->post('flag'),
                'categ' => (int) request()->post('categ'),
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'editsect' && $request->isMethod('post')) {
            app(InfoRepository::class)->updateFaq((int) request()->post('id'), [
                'question' => (string) request()->post('title'),
                'answer' => '',
                'flag' => (int) request()->post('flag'),
                'categ' => 0,
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'delete') {
            $id = (int) (request()->query('id') ?? 0);
            if (request()->query('confirm') === 'yes') {
                app(InfoRepository::class)->deleteFaq($id);

                return redirect($redirectBase.'/faqmanage.php');
            }

            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'confirm_delete',
                'id' => $id,
            ]);
        }

        if ($action === 'addnewitem' && $request->isMethod('post')) {
            $categ = (int) (request()->post('categ') ?? 0);
            $langId = (int) (request()->post('langid') ?? 0);
            $max = app(InfoRepository::class)->getFaqMaxOrderAndLinkId('item', $langId);
            app(InfoRepository::class)->insertFaq([
                'link_id' => $max['maxlinkid'] + 1,
                'type' => 'item',
                'lang_id' => $langId,
                'question' => (string) request()->post('question'),
                'answer' => (string) request()->post('answer'),
                'flag' => (int) (request()->post('flag') ?? 0),
                'categ' => $categ,
                'order' => $max['maxorder'] + 1,
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'addnewsect' && $request->isMethod('post')) {
            $language = (int) (request()->post('language') ?? 0);
            $max = app(InfoRepository::class)->getFaqMaxOrderAndLinkId('categ', $language);
            app(InfoRepository::class)->insertFaq([
                'link_id' => $max['maxlinkid'] + 1,
                'type' => 'categ',
                'lang_id' => $language,
                'question' => (string) request()->post('title'),
                'answer' => '',
                'flag' => (int) (request()->post('flag') ?? 0),
                'categ' => 0,
                'order' => $max['maxorder'] + 1,
            ]);

            return redirect($redirectBase.'/faqmanage.php');
        }

        if ($action === 'edit') {
            $id = (int) (request()->query('id') ?? 0);
            $arr = app(InfoRepository::class)->getFaqById($id);
            if ($arr === null) {
                return $this->legacyAbortResponse('Error', 'Invalid id');
            }
            $arr['question'] = htmlspecialchars((string) $arr['question']);
            $arr['answer'] = htmlspecialchars((string) $arr['answer']);

            $categories = [];
            if ($arr['type'] === 'item') {
                $categories = app(InfoRepository::class)->getFaqCategoriesByLang((int) $arr['lang_id']);
            } elseif ($arr['type'] === 'categ') {
                $arr['lang_name'] = app(InfoRepository::class)->getLanguageName((int) $arr['lang_id']);
            }

            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'edit',
                'arr' => $arr,
                'categories' => $categories,
            ]);
        }

        if ($action === 'additem') {
            $inId = (int) (request()->query('inid') ?? 0);
            $langId = (int) (request()->query('langid') ?? 0);

            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'additem',
                'inid' => $inId,
                'langid' => $langId,
            ]);
        }

        if ($action === 'addsection') {
            $languages = Locale::languageList('rule_lang', null);
            $defLang = app(Globals::class)->get('deflang', '');

            return $this->legacyPage($request, 'faqactions', true, [
                'mode' => 'addsection',
                'languages' => $languages,
                'deflang' => $defLang,
            ]);
        }

        return redirect($redirectBase.'/faqmanage.php');
    }
}
