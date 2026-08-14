<?php

namespace App\Http\Controllers;

use App\Repositories\InfoRepository;
use App\Support\Locale;
use App\Support\SupportContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class InfoController extends LegacyController
{
    public function getrss(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'getrss');
    }

    public function userhistory(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'userhistory');
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
