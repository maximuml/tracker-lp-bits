<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\SearchPageRepository;
use App\Services\Legacy\LegacyPartialRenderer;
use App\Support\SupportContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class UtilityController extends LegacyController
{
    private LegacyPartialRenderer $renderer;

    public function __construct(LegacyPartialRenderer $renderer)
    {
        $this->renderer = $renderer;
    }

    public function search(Request $request): View|RedirectResponse
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find($curUser['id'] ?? 0) : null;
        if ($currentUser === null) {
            $qs = $request->getQueryString();

            return redirect('/search.php' . ($qs ? '?' . $qs : ''));
        }

        $data = SearchPageRepository::dataForSearch($request, $currentUser);

        return $this->legacyPage($request, 'search', true, $data);
    }

    public function usersearch(Request $request): View|RedirectResponse
    {
        $result = $this->renderer->render('usersearch');
        if ($result instanceof RedirectResponse) {
            return $result;
        }

        return $this->legacyPage($request, 'usersearch', true, $result);
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if (SupportContext::getCache() === null) {
            $qs = $request->getQueryString();

            return redirect('/ajax.php' . ($qs ? '?' . $qs : ''));
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('params', []);

        $passkeyActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $passkeyActions, true)) {
            \App\Support\LegacyAuth::requireLoginFromContext();
        }

        if (! class_exists('AjaxInterface')) {
            view('ajax._ajax')->render();
        }

        try {
            $callable = ['AjaxInterface', $action];
            if (! is_callable($callable)) {
                $currentUser = SupportContext::getUser() ?? [];
                \App\Support\Logger::writeWithContext((string) ("hacking attempt made by " . ($currentUser['username'] ?? 'guest') . ",uid " . ($currentUser['id'] ?? 0)), (string) 'error', (bool) false);
                throw new \RuntimeException("Invalid action: {$action}");
            }

            $result = call_user_func($callable, $params);

            return response()->json(\App\Support\Api::successWithContext($result));
        } catch (\Throwable $exception) {
            \App\Support\Logger::writeWithContext((string) ($exception->getMessage() . $exception->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(\App\Support\Api::failWithContext($exception->getMessage(), $request->all()));
        }
    }

    public function attachment(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'attachment', true);
    }

    public function getattachment(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'getattachment', true);
    }

    public function image(Request $request): Response|RedirectResponse
    {
        $action = (string) $request->input('action', '');
        $imagehash = (string) $request->input('imagehash', '');

        if ($action !== 'regimage') {
            return response('Invalid captcha action', 404);
        }

        $driver = \App\Support\Captcha::manager()->driver('image');

        if (! method_exists($driver, 'outputImage')) {
            return response('Captcha driver does not support image rendering', 404);
        }

        ob_start();
        $driver->outputImage($imagehash);
        $content = ob_get_clean();

        $headers = [];
        $status = http_response_code();
        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $headers[$name] = ($headers[$name] ?? '') !== '' ? $headers[$name] . ', ' . $value : $value;
                header_remove($name);
            }
        }

        $responseStatus = ($status >= 100) ? $status : 200;

        return response($content, $responseStatus, $headers);
    }

    public function page(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'page', false);
    }

    public function tags(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'tags', false);
    }

    public function suggest(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageRaw($request, 'suggest', false);
    }

    public function preview(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'preview', true);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'moresmilies', true);
    }

    public function smilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'smilies', true);
    }

    public function opensearch(Request $request): Response
    {
        $xml = Cache::remember('opensearch_description', 86400, function () {
            return $this->buildOpensearchXml();
        });

        return response((string) $xml, 200, ['Content-Type' => 'text/xml']);
    }

    private function buildOpensearchXml(): string
    {
        $siteName = (string) (SupportContext::getGlobal('SITENAME', '') ?? '');
        $siteEmail = (string) (SupportContext::getGlobal('SITEEMAIL', '') ?? '');
        $slogan = (string) (SupportContext::getGlobal('SLOGAN', '') ?? '');
        $baseUrl = (string) (SupportContext::getGlobal('BASEURL', '') ?? '');
        $dateFounded = (string) (SupportContext::getGlobal('datefounded', '') ?? '');
        $projectName = (string) (SupportContext::getGlobal('PROJECTNAME', '') ?? '');

        $url = \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . $baseUrl;
        $year = substr($dateFounded, 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $attribution = "Copyright (c) " . $siteName . " " . (date("Y") != $yearFounded ? $yearFounded . "-" : "") . date("Y") . ", all rights reserved";

        $faviconPath = public_path('favicon.ico');
        $faviconData = is_file($faviconPath)
            ? 'data:image/x-icon;base64,' . base64_encode((string) file_get_contents($faviconPath))
            : $url . '/favicon.ico';

        $siteNameEsc = htmlspecialchars($siteName);
        $sloganEsc = htmlspecialchars($slogan);

        return <<<XML
<?xml version="1.0" encoding="utf-8"?>
<OpenSearchDescription xmlns="http://a9.com/-/spec/opensearch/1.1/"
    xmlns:moz="http://www.mozilla.org/2006/browser/search/">
    <ShortName>{$siteNameEsc} Torrents</ShortName>
    <Description>Search Torrents at {$siteNameEsc} - {$sloganEsc}.</Description>
    <Url type="text/html"
        rel="results"
        pageOffset="0"
              template="{$url}/torrents.php?search={searchTerms}&amp;page={startPage?}" />
    <Url type="application/rss+xml"
        rel="results"
        indexOffset="0"
        template="{$url}/torrentrss.php?search={searchTerms}&amp;rows={count?}&amp;startindex={startIndex?}" />
    <Url type="application/opensearchdescription+xml"
        rel="self"
        template="{$url}/opensearch.php" />
    <Url type="application/x-suggestions+json"
        rel="suggestions"
        template="{$url}/searchsuggest.php?q={searchTerms}" />
    <Contact>{$siteEmail}</Contact>
    <Tags>Torrents {$projectName}</Tags>
    <LongName>{$siteNameEsc} Torrents Search</LongName>
    <Image height="32" width="32" type="image/x-icon">{$faviconData}</Image>
    <Image height="32" width="32" type="image/x-icon">{$url}/favicon.ico</Image>
    <moz:SearchForm>{$url}/torrents.php</moz:SearchForm>
    <Query role="example" searchTerms="batman" />
    <Developer>{$siteNameEsc} Staff</Developer>
    <Attribution>{$attribution}</Attribution>
    <SyndicationRight>limited</SyndicationRight>
    <Language>*</Language>
    <InputEncoding>UTF-8</InputEncoding>
    <OutputEncoding>UTF-8</OutputEncoding>
</OpenSearchDescription>
XML;
    }

    public function confirmemail(Request $request): Response|RedirectResponse
    {
        return $this->legacyPageWithRedirect($request, 'confirmemail', false);
    }

    public function ok(Request $request): View|RedirectResponse
    {
        $type = (string) $request->input('type', '');
        $email = '';
        if ($type === 'signup') {
            $email = (string) $request->input('email', '');
        }

        /** @var array<string, string> $langOk */
        $langOk = (array) SupportContext::getGlobal('lang_ok', []);
        $title = match ($type) {
            'adminactivate', 'inviter', 'signup' => $langOk['head_user_signup'] ?? '',
            'sysop' => $langOk['head_sysop_activation'] ?? '',
            'confirmed' => $langOk['head_already_confirmed'] ?? '',
            'confirm' => $langOk['head_signup_confirmation'] ?? '',
            default => '',
        };

        return $this->legacyPage($request, 'ok', false, [
            'type' => $type,
            'email' => $email,
            'title' => $title,
            'siteName' => Setting::getSiteName(),
            'CURUSER' => SupportContext::getUser(),
        ]);
    }
}
