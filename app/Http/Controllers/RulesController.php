<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\InfoRepositoryInterface;
use App\Models\Setting;
use App\Support\Locale;
use App\Support\RedisGuard;
use App\Support\Url;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RulesController extends BasePageController
{
    public function __construct(
        private readonly InfoRepositoryInterface $infoRepository,
    ) {}

    public function rules(Request $request): Response|RedirectResponse
    {
        $langFolder = Locale::currentLangDir('en');
        $cacheKey = "{$langFolder}_rules";

        $rules = RedisGuard::remember($cacheKey, 900, function () {
            $langId = $this->infoRepository->resolveRuleLangId(Locale::guestIdWithContext());

            return $this->infoRepository->rules($langId);
        });

        return response(view('rules.index', ['rules' => $rules])->render());
    }

    public function userAgreement(Request $request): View|RedirectResponse|Response
    {
        return $this->renderPage($request, 'useragreement', false, [
            'SITENAME' => Setting::getSiteName(),
            'BASEURL' => Url::schemeAndHost(false),
        ]);
    }

    public function aboutNexus(Request $request): View|RedirectResponse|Response
    {
        return $this->renderPage($request, 'aboutnexus', false, $this->infoRepository->aboutNexus());
    }
}
