<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class AdminController extends LegacyController
{
    public function donorlist(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'donorlist');

    }

    public function stats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'stats');

    }

    public function warned(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'warned');

    }

    public function nowarn(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'nowarn');

    }

    public function allagents(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'allagents');

    }

    public function checkuser(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'checkuser');

    }

    public function takeconfirm(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takeconfirm');

    }

    public function userBanLog(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'user-ban-log');

    }

    public function clearCache(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'clearcache');

    }

    public function catmanage(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'catmanage');

    }

    public function fields(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'fields');

    }

    public function formats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'formats');

    }

    public function videoformats(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'videoformats');

    }

    public function settings(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'settings', true);

    }

    public function users(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'users', true);

    }

    public function location(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'location', true);

    }

    public function reset(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'reset', true);

    }

    public function selfEnable(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'self-enable', true);

    }

    public function unco(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'unco', true);

    }

    public function adduser(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'adduser', true);

    }

    public function testip(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'testip', true);

    }

}