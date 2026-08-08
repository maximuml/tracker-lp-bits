<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StaffController extends LegacyController
{
    public function modtask(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'modtask');

    }

    public function staff(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'staff');

    }

    public function staffbox(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'staffbox');

    }

    public function staffmess(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'staffmess');

    }

    public function takeStaffmess(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takestaffmess');

    }

    public function contactstaff(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'contactstaff');

    }

    public function takecontact(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'takecontact');

    }

    public function modrules(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageWithRedirect($request, 'modrules');

    }

    public function staffpanel(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'staffpanel', true);

    }

}