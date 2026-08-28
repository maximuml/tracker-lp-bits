<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Repositories\InfoRepository;
use App\Support\Globals;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RulesController extends LegacyController
{
    public function rules(Request $request): Response|RedirectResponse
    {
        $langFolder = (string) app(Globals::class)->get('CURLANGDIR', 'en');
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

    public function aboutNexus(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'aboutnexus', false, InfoRepository::aboutNexus());
    }
}
