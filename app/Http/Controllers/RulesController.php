<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Repositories\InfoRepository;
use App\Support\Locale;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class RulesController extends LegacyController
{
    public function __construct(
        private readonly InfoRepository $infoRepository,
    ) {}

    public function rules(Request $request): Response|RedirectResponse
    {
        $langFolder = Locale::currentLangDir('en');
        $cacheKey = "{$langFolder}_rules";

        $html = Cache::remember($cacheKey, 900, function () {
            $langId = $this->infoRepository->resolveRuleLangId(Locale::guestIdWithContext());

            return view('rules.index', ['rules' => $this->infoRepository->rules($langId)])->render();
        });

        return response($html);
    }

    public function userAgreement(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'useragreement', false, [
            'SITENAME' => Setting::getSiteName(),
            'BASEURL' => Url::schemeAndHost(false),
        ]);
    }

    public function aboutNexus(Request $request): View|RedirectResponse|Response
    {
        return $this->legacyPage($request, 'aboutnexus', false, $this->infoRepository->aboutNexus());
    }
}
