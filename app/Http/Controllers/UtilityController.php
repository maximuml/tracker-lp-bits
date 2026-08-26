<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\SearchPageRepository;
use App\Services\Legacy\AjaxService;
use App\Services\Legacy\AttachmentLegacyService;
use App\Services\UsersearchPageService;
use App\Support\Api;
use App\Support\Attachment\AttachmentService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Captcha;
use App\Support\Http;
use App\Support\LegacyAuth;
use App\Support\Logger;
use App\Support\Strings;
use App\Support\Style;
use App\Support\SupportContext;
use App\Support\Url;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UtilityController extends LegacyController
{
    private UsersearchPageService $usersearchPageService;

    public function __construct(UsersearchPageService $usersearchPageService)
    {
        $this->usersearchPageService = $usersearchPageService;
    }

    public function search(Request $request): View|RedirectResponse
    {
        $curUser = SupportContext::getUser() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;
        if ($currentUser === null) {
            $qs = $request->getQueryString();

            return redirect('/search.php'.($qs ? '?'.$qs : ''));
        }

        $data = SearchPageRepository::dataForSearch($request, $currentUser);

        return $this->legacyPage($request, 'search', true, $data);
    }

    public function usersearch(Request $request): View|Response|RedirectResponse
    {
        $data = $this->usersearchPageService->build($request);

        return $this->legacyPage($request, 'usersearch', true, $data);
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if (app(LegacyRedisCache::class) === null) {
            $qs = $request->getQueryString();

            return redirect('/ajax.php'.($qs ? '?'.$qs : ''));
        }

        $action = (string) $request->input('action', '');
        $params = $request->input('params', []);

        $passkeyActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $passkeyActions, true)) {
            LegacyAuth::requireLoginFromContext();
        }

        if (! in_array($action, AjaxService::ALLOWED_ACTIONS, true)) {
            $currentUser = SupportContext::getUser() ?? [];
            Logger::writeWithContext((string) ('hacking attempt made by '.($currentUser['username'] ?? 'guest').',uid '.($currentUser['id'] ?? 0)), (string) 'error', (bool) false);

            return response()->json(Api::call(1, "Invalid action: {$action}", $request->all()));
        }

        try {
            $result = AjaxService::{$action}($params);

            return response()->json(Api::successWithContext($result));
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(Api::failWithContext($exception->getMessage(), $request->all()));
        }
    }

    public function attachment(Request $request): Response
    {
        $currentUser = SupportContext::getUser() ?? [];
        $Attach = new AttachmentService((int) ($currentUser['id'] ?? 0));

        $count_limit = (int) $Attach->get_count_limit();
        $count_left = $Attach->get_count_left();
        $size_limit = $Attach->get_size_limit_byte();
        $allowed_exts = $Attach->get_allowed_ext();

        $altsize = (string) $request->input('altsize', '');
        $callback_func = (string) $request->input('callback_func', '');
        $warning = '';
        $script = '';

        if ($request->isMethod('POST') && $Attach->enable_attachment()) {
            $uploaded = $request->file('file');
            $file = null;
            if ($uploaded !== null) {
                $file = [
                    'tmp_name' => $uploaded->getPathname(),
                    'size' => $uploaded->getSize(),
                    'type' => $uploaded->getMimeType(),
                    'name' => $uploaded->getClientOriginalName(),
                ];
            }

            $lang_attachment = (array) (SupportContext::getGlobal('lang_attachment') ?? []);
            $result = AttachmentLegacyService::processUpload($currentUser, $Attach, $lang_attachment, $altsize, $callback_func, $file);
            $warning = (string) ($result['warning'] ?? '');
            $script = (string) ($result['script'] ?? '');
            $count_left = (int) ($result['count_left'] ?? $count_left);
        }

        $content = view('attachment.index', [
            'CURUSER' => $currentUser,
            'lang_attachment' => (array) (SupportContext::getGlobal('lang_attachment') ?? []),
            'Attach' => $Attach,
            'count_limit' => $count_limit,
            'count_left' => $count_left,
            'size_limit' => $size_limit,
            'allowed_exts' => $allowed_exts,
            'css_uri' => Style::cssUriWithContext(),
            'altsize' => $altsize,
            'callback_func' => $callback_func,
            'warning' => $warning,
            'script' => $script,
        ])->render();

        return response($content, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function getattachment(Request $request): Response|RedirectResponse|StreamedResponse
    {
        $id = (int) $request->input('id', 0);
        $dlkey = (string) $request->input('dlkey', '');

        if ($id <= 0 || $dlkey === '') {
            return response('Invalid id or key.', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $row = (array) DB::table('attachments')->where('id', $id)->where('dlkey', $dlkey)->first();
        if (! $row) {
            return response('No attachment found.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $httpdirectory = (string) SupportContext::getGlobal('httpdirectory_attachment', '');
        $basePath = realpath($httpdirectory);
        $filelocation = $httpdirectory.'/'.$row['location'];
        $realFile = realpath($filelocation);

        if ($basePath === false || $realFile === false || ! str_starts_with($realFile, $basePath) || ! is_file($realFile) || ! is_readable($realFile)) {
            return response('File not found or cannot be read.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $filename = basename((string) ($row['filename'] ?? ''));
        $filename = str_replace(['"', '\\', "\r", "\n"], '', $filename);
        if ($filename === '') {
            $filename = 'attachment';
        }

        DB::table('attachments')->where('id', $id)->increment('downloads');

        $cache = app(LegacyRedisCache::class);
        if ($cache !== null) {
            $cache->delete_value('attachment_'.$dlkey.'_content');
        }

        return new StreamedResponse(function () use ($realFile) {
            $f = fopen($realFile, 'rb');
            if (! $f) {
                return;
            }

            while (! feof($f)) {
                echo fread($f, 4096);
            }

            fclose($f);
        }, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function image(Request $request): Response|RedirectResponse
    {
        $action = (string) $request->input('action', '');
        $imagehash = (string) $request->input('imagehash', '');

        if ($action !== 'regimage') {
            return response('Invalid captcha action', 404);
        }

        $driver = Captcha::manager()->driver('image');

        if (! method_exists($driver, 'outputImage')) {
            return response('Captcha driver does not support image rendering', 404);
        }

        ob_start();
        $driver->outputImage($imagehash);
        $content = ob_get_clean() ?: '';

        $headers = [];
        $status = http_response_code();
        foreach (headers_list() as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $headers[$name] = ($headers[$name] ?? '') !== '' ? $headers[$name].', '.$value : $value;
                header_remove($name);
            }
        }

        $responseStatus = is_int($status) && $status >= 100 ? $status : 200;

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

    public function suggest(Request $request): Response
    {
        $headers = [
            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
            'Last-Modified' => gmdate('D, d M Y H:i:s').' GMT',
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Content-Type' => 'text/xml; charset=utf-8',
        ];

        $q = trim((string) $request->input('q', ''));
        if ($q === '') {
            return response('', 200, $headers);
        }

        $suggestRows = DB::table('suggest')
            ->selectRaw('keywords AS suggest, COUNT(*) AS count')
            ->where('keywords', 'like', $q.'%')
            ->groupBy('keywords')
            ->orderByDesc('count')
            ->orderByDesc('keywords')
            ->limit(10)
            ->get();

        $result = '';
        $i = 0;
        foreach ($suggestRows as $suggest) {
            $suggest = (array) $suggest;
            if (strlen((string) $suggest['suggest']) > 25) {
                continue;
            }
            $result .= ($result === '' ? '' : "\r\n").$suggest['suggest']."\r\n".$suggest['count'];
            $i++;
            if ($i >= 5) {
                break;
            }
        }

        return response($result, 200, $headers);
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

        $url = Http::protocolPrefix(Url::isSecure()).$baseUrl;
        $year = substr($dateFounded, 0, 4);
        $yearFounded = $year !== '' ? $year : '2007';
        $attribution = 'Copyright (c) '.$siteName.' '.(date('Y') != $yearFounded ? $yearFounded.'-' : '').date('Y').', all rights reserved';

        $faviconPath = public_path('favicon.ico');
        $faviconData = is_file($faviconPath)
            ? 'data:image/x-icon;base64,'.base64_encode((string) file_get_contents($faviconPath))
            : $url.'/favicon.ico';

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
        $routePath = $request->route('path') ?? '';
        $pathInfo = $routePath !== '' ? '/'.ltrim((string) $routePath, '/') : '';
        if (! preg_match(':^/(\d{1,10})/([\w]{32})/(.+)$:', $pathInfo, $matches)) {
            abort(404);
        }

        $id = (int) $matches[1];
        $md5 = $matches[2];
        $email = urldecode($matches[3]);

        if ($id <= 0) {
            abort(404);
        }

        $validator = validator(['email' => $email], [
            'email' => 'required|email|max:255',
        ]);
        if ($validator->fails()) {
            abort(404);
        }

        $user = User::query()->where('id', $id)->first(['editsecret']);
        if (! $user) {
            abort(404);
        }

        $sec = Strings::padHash($user->editsecret);
        if (preg_match('/^ *$/s', $sec) || $md5 !== md5($sec.$email.$sec)) {
            abort(404);
        }

        $affected = User::query()->where('id', $id)->where('editsecret', $user->editsecret)->update(['editsecret' => '', 'email' => $email]);
        if (! $affected) {
            abort(404);
        }

        return redirect('/usercp.php?action=security&type=saved');
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
