<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\InfoRepositoryInterface;
use App\Models\Setting;
use App\Support\Locale;
use App\Support\RedisGuard;
use App\Support\Url;
use App\ViewModels\AboutnexusPageViewModel;
use App\ViewModels\UseragreementPageViewModel;
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

        return response(view('rules.index', ['rules' => array_map($this->ruleView(...), $rules)])->render());
    }

    /**
     * Rule titles are stored as legacy HTML ("Title - <tag class=striking>Warning</tag>");
     * split them into plain-text heading and emphasis instead of printing the markup.
     *
     * @param  array<string, mixed>  $rule
     * @return array{heading: string, emphasis: string, text: string}
     */
    private function ruleView(array $rule): array
    {
        $title = (string) ($rule['title'] ?? '');
        $plain = static fn (string $html): string => trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $heading = $plain($title);
        $emphasis = '';
        if (preg_match('~^([^<]*)<([a-z]+)\b[^>]*>(.*?)</\2>~is', $title, $m) === 1 && trim($m[1]) !== '') {
            $heading = rtrim($plain($m[1]), " \t-–—:");
            $emphasis = $plain($m[3]);
        }

        return ['heading' => $heading, 'emphasis' => $emphasis, 'text' => (string) ($rule['text'] ?? '')];
    }

    public function userAgreement(Request $request): View|RedirectResponse|Response
    {
        return $this->renderPage($request, 'useragreement', false, [
            'vm' => UseragreementPageViewModel::fromPageData([
                'SITENAME' => Setting::getSiteName(),
                'BASEURL' => Url::schemeAndHost(false),
            ])]);
    }

    public function aboutNexus(Request $request): View|RedirectResponse|Response
    {
        return $this->renderPage($request, 'aboutnexus', false, [
            'vm' => AboutnexusPageViewModel::fromPageData($this->infoRepository->aboutNexus()),
        ]);
    }
}
