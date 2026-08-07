<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
