<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class BonusController extends LegacyController
{
    public function bonusLog(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'bonus-log', true);

    }

    public function medal(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'medal', true);

    }

    public function task(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'task', true);

    }

    public function uploaders(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'uploaders', true);

    }

    public function freeleech(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'freeleech', true);

    }

    public function magic(Request $request): Response|RedirectResponse
    {

        return $this->legacyPageRaw($request, 'magic', true);

    }

}