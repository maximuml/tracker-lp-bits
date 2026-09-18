<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Repositories\SearchPageRepository;
use App\Services\AjaxService;
use App\Services\AttachmentMutationService;
use App\Services\SecureTokenService;
use App\Services\UsersearchPageService;
use App\Support\Api;
use App\Support\Attachment\AttachmentService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Captcha;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\Http;
use App\Support\LegacyAuth;
use App\Support\LegacyHeaderBag;
use App\Support\Logger;
use App\Support\Smilies;
use App\Support\Style;
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

    private SearchPageRepository $searchPageRepository;

    private CurrentUser $currentUser;

    private Globals $globals;

    private ?LegacyRedisCache $legacyRedisCache;

    private LegacyHeaderBag $legacyHeaderBag;

    public function __construct(
        UsersearchPageService $usersearchPageService,
        private readonly AjaxService $ajaxService,
        SearchPageRepository $searchPageRepository,
        CurrentUser $currentUser,
        Globals $globals,
        ?LegacyRedisCache $legacyRedisCache,
        LegacyHeaderBag $legacyHeaderBag,
        private readonly SecureTokenService $secureTokenService,
    ) {
        $this->usersearchPageService = $usersearchPageService;
        $this->searchPageRepository = $searchPageRepository;
        $this->currentUser = $currentUser;
        $this->globals = $globals;
        $this->legacyRedisCache = $legacyRedisCache;
        $this->legacyHeaderBag = $legacyHeaderBag;
    }

    public function search(Request $request): View|RedirectResponse
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUser = ! empty($curUser) ? User::query()->find((int) ($curUser['id'] ?? 0)) : null;
        if ($currentUser === null) {
            $qs = $request->getQueryString();

            return redirect('/search.php'.($qs ? '?'.$qs : ''));
        }

        $data = $this->searchPageRepository->dataForSearch($request, $currentUser);

        return $this->legacyPage($request, 'search', true, $data);
    }

    public function usersearch(Request $request): View|Response|RedirectResponse
    {
        $data = $this->usersearchPageService->build($request)->toArray();

        return $this->legacyPage($request, 'usersearch', true, $data);
    }

    public function ajax(Request $request): JsonResponse|RedirectResponse
    {
        if ($this->legacyRedisCache === null) {
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
            $currentUser = $this->currentUser->get() ?? [];
            Logger::writeWithContext((string) ('hacking attempt made by '.($currentUser['username'] ?? 'guest').',uid '.($currentUser['id'] ?? 0)), (string) 'error', (bool) false);

            return response()->json(Api::call(1, "Invalid action: {$action}", $request->only(['action', 'params'])));
        }

        try {
            $result = $this->ajaxService->dispatch($action, $params);

            return response()->json(Api::successWithContext($result));
        } catch (\Throwable $exception) {
            Logger::writeWithContext((string) ($exception->getMessage().$exception->getTraceAsString()), (string) 'error', (bool) false);

            return response()->json(Api::failWithContext($exception->getMessage(), $request->only(['action', 'params'])));
        }
    }

    public function attachment(Request $request): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($currentUser['id'] ?? 0));

        return $this->renderAttachment($request, $currentUser, $Attach);
    }

    public function attachmentStore(Request $request): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($currentUser['id'] ?? 0));

        $warning = '';
        $script = '';
        $countLeft = null;

        if ($Attach->enable_attachment()) {
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

            $altsize = (string) $request->input('altsize', '');
            $callbackFunc = (string) $request->input('callback_func', '');
            $result = AttachmentMutationService::processUpload($currentUser, $Attach, $altsize, $callbackFunc, $file);
            $warning = (string) ($result['warning'] ?? '');
            $script = (string) ($result['script'] ?? '');
            $countLeft = isset($result['count_left']) ? (int) $result['count_left'] : null;
        }

        return $this->renderAttachment($request, $currentUser, $Attach, $warning, $script, $countLeft);
    }

    /**
     * @param  array<string, mixed>  $currentUser
     */
    private function renderAttachment(Request $request, array $currentUser, AttachmentService $Attach, string $warning = '', string $script = '', ?int $countLeft = null): Response
    {
        $allowedextsblock = rtrim(implode('/', $Attach->get_allowed_ext()), '/');
        if ($allowedextsblock === '') {
            $allowedextsblock = 'N/A';
        }

        $cspNonce = (string) $request->attributes->get('csp_nonce', '');
        if ($script !== '' && $cspNonce !== '') {
            $script = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.htmlspecialchars($cspNonce, ENT_QUOTES).'"', $script);
        }

        $content = view('attachment.index', [
            'CURUSER' => $currentUser,
            'Attach' => $Attach,
            'enableAttachment' => $Attach->enable_attachment(),
            'count_limit' => (int) $Attach->get_count_limit(),
            'count_left' => $countLeft ?? $Attach->get_count_left(),
            'size_limit' => $Attach->get_size_limit_byte(),
            'allowedextsblock' => $allowedextsblock,
            'css_uri' => Style::cssUriWithContext(),
            'altsize' => (string) $request->input('altsize', ''),
            'callback_func' => (string) $request->input('callback_func', ''),
            'warning' => $warning,
            'script' => SafeHtml::fromTrustedHtml($script),
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

        $httpdirectory = (string) $this->globals->get('httpdirectory_attachment', '');
        // savedirectory is resolved against ROOT_PATH on upload; resolve the
        // same way here — a bare relative path would resolve against the
        // php-fpm CWD (public/) and miss the real location.
        $basePath = realpath(base_path($httpdirectory));
        $realFile = realpath(base_path($httpdirectory.'/'.$row['location']));

        if ($basePath === false || $realFile === false || ! str_starts_with($realFile, $basePath) || ! is_file($realFile) || ! is_readable($realFile)) {
            return response('File not found or cannot be read.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $filename = basename((string) ($row['filename'] ?? ''));
        $filename = str_replace(['"', '\\', "\r", "\n"], '', $filename);
        if ($filename === '') {
            $filename = 'attachment';
        }

        DB::table('attachments')->where('id', $id)->increment('downloads');

        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('attachment_'.$dlkey.'_content');
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
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Redirect' => '/attachments/'.$row['location'],
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

        // T-11: Read from the per-request LegacyHeaderBag instead of SAPI
        // globals that leak state across Octane worker requests.
        $headerBag = $this->legacyHeaderBag;
        $status = $headerBag->getStatusCode();
        $headers = $headerBag->toResponseHeaders();
        $headerBag->flush();

        $responseStatus = $status !== null && $status >= 100 ? $status : 200;

        return response($content, $responseStatus, $headers);
    }

    public function page(Request $request): Response|RedirectResponse
    {
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

        $view = $request->input('view');
        if (! empty($view)) {
            $view = str_replace('.', '/', trim((string) $view, '/.'));
            $viewFile = ROOT_PATH."resources/views/$view";
            if (! str_ends_with($viewFile, '.php')) {
                $viewFile .= '.php';
            }
            if (file_exists($viewFile)) {
                ob_start();
                require $viewFile;

                return response(ob_get_clean() ?: '');
            }
            $msg = "viewFile: $viewFile not exists, _REQUEST: ".json_encode($request->all());
            Logger::writeWithContext($msg, 'error', false);
            throw new \RuntimeException($msg);
        }

        $msg = 'require view parameter, _REQUEST: '.json_encode($request->all());
        Logger::writeWithContext($msg, 'error', false);
        abort(400, 'require view parameter');
    }

    public function tags(Request $request): View|RedirectResponse
    {
        $siteName = Setting::getSiteName();
        $username = (string) (($this->currentUser->get() ?? [])['username'] ?? '');

        return $this->legacyPage($request, 'tags', false, [
            'test' => (string) $request->post('test', ''),
            'siteName' => $siteName,
            'tagItems' => $this->tagItems($siteName, $username),
        ]);
    }

    /**
     * @return list<array<string, string|SafeHtml>>
     */
    private function tagItems(string $siteName, string $username): array
    {
        $schemeHost = Url::schemeAndHost(false);
        $t = fn (string $key): string => (string) __('legacy/tags.'.$key);
        $tag = function (string $name, string $description, string $syntax, string $example, string $remarks = ''): array {
            return [
                'name' => SafeHtml::fromTrustedHtml($name),
                'description' => SafeHtml::fromTrustedHtml($description),
                'syntax' => SafeHtml::fromTrustedHtml($syntax),
                'example' => SafeHtml::fromTrustedHtml($example),
                'result' => Format::formatComment($example),
                'remarks' => SafeHtml::fromTrustedHtml($remarks),
            ];
        };

        return [
            $tag($t('text_bold'), $t('text_bold_description'), $t('text_bold_syntax'), $t('text_bold_example')),
            $tag($t('text_italic'), $t('text_italic_description'), $t('text_italic_syntax'), $t('text_italic_example')),
            $tag($t('text_underline'), $t('text_underline_description'), $t('text_underline_syntax'), $t('text_underline_example')),
            $tag($t('text_strikethrough'), $t('text_strikethrough_description'), $t('text_strikethrough_syntax'), $t('text_strikethrough_example')),
            $tag($t('text_hide'), $t('text_hide_description'), $t('text_hide_syntax'), $t('text_hide_example')),
            $tag($t('text_color_one'), $t('text_color_one_description'), $t('text_color_one_syntax'), $t('text_color_one_example'), $t('text_color_one_remarks')),
            $tag($t('text_color_two'), $t('text_color_two_description'), $t('text_color_two_syntax'), $t('text_color_two_example'), $t('text_color_two_remarks')),
            $tag($t('text_size'), $t('text_size_description'), $t('text_size_syntax'), $t('text_size_example'), $t('text_size_remarks')),
            $tag($t('text_font'), $t('text_font_description'), $t('text_font_syntax'), $t('text_font_example'), $t('text_font_remarks')),
            $tag($t('text_hyperlink_one'), $t('text_hyperlink_one_description'), $t('text_hyperlink_one_syntax'), sprintf($t('text_hyperlink_one_example'), $schemeHost), $t('text_hyperlink_one_remarks')),
            $tag($t('text_hyperlink_two'), $t('text_hyperlink_two_description'), $t('text_hyperlink_two_syntax'), sprintf($t('text_hyperlink_two_example'), $schemeHost, $siteName), $t('text_hyperlink_two_remarks')),
            $tag($t('text_image_one'), $t('text_image_one_description'), $t('text_image_one_syntax'), sprintf($t('text_image_one_example'), $schemeHost), $t('text_image_one_remarks')),
            $tag($t('text_image_two'), $t('text_image_two_description'), $t('text_image_two_syntax'), sprintf($t('text_image_two_example'), $schemeHost), $t('text_image_two_remarks')),
            $tag($t('text_quote_one'), $t('text_quote_one_description'), $t('text_quote_one_syntax'), sprintf($t('text_quote_one_example'), $siteName)),
            $tag($t('text_quote_two'), $t('text_quote_two_description'), $t('text_quote_two_syntax'), sprintf($t('text_quote_two_example'), $username, $siteName)),
            $tag($t('text_list'), $t('text_description'), $t('text_list_syntax'), $t('text_list_example')),
            $tag($t('text_preformat'), $t('text_preformat_description'), $t('text_preformat_syntax'), $t('text_preformat_example')),
            $tag($t('text_code'), $t('text_code_description'), $t('text_code_syntax'), $t('text_code_example')),
            $tag($t('text_site'), $t('text_site_description'), $t('text_site_syntax'), $t('text_site_example')),
            $tag($t('text_siteurl'), $t('text_siteurl_description'), $t('text_siteurl_syntax'), $t('text_siteurl_example')),
            $tag($t('text_left'), $t('text_left_description'), $t('text_left_syntax'), $t('text_left_example')),
            $tag($t('text_center'), $t('text_center_description'), $t('text_center_syntax'), $t('text_center_example')),
            $tag($t('text_right'), $t('text_right_description'), $t('text_right_syntax'), $t('text_right_example')),
            $tag($t('text_youtube'), $t('text_youtube_description'), $t('text_youtube_syntax'), $t('text_youtube_example')),
            $tag($t('text_video'), $t('text_video_description'), $t('text_video_syntax'), $t('text_video_example')),
            $tag($t('text_audio'), $t('text_audio_description'), $t('text_audio_syntax'), $t('text_audio_example')),
            $tag($t('text_spoiler'), $t('text_spoiler_description'), $t('text_spoiler_syntax'), $t('text_spoiler_example')),
            $tag($t('text_hr'), $t('text_hr_description'), $t('text_hr_syntax'), $t('text_hr_example')),
        ];
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
        return $this->legacyPage($request, 'preview', true, [
            'body' => (string) $request->post('body', ''),
        ]);
    }

    public function moresmilies(Request $request): View|RedirectResponse
    {
        return $this->legacyPage($request, 'moresmilies', true, [
            'form' => (string) $request->query('form', ''),
            'text' => (string) $request->query('text', ''),
        ]);
    }

    public function smilies(Request $request): View|RedirectResponse
    {

        return $this->legacyPage($request, 'smilies', true, [
            'smiliesFrame' => SafeHtml::fromTrustedHtml(Smilies::framedTable(
                (string) (__('legacy/functions.text_smilies')),
                (string) (__('legacy/functions.col_type_something')),
                (string) (__('legacy/functions.col_to_make_a')),
            )),
        ]);
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
        $siteName = (string) ($this->globals->get('SITENAME', '') ?? '');
        $siteEmail = (string) ($this->globals->get('SITEEMAIL', '') ?? '');
        $slogan = (string) ($this->globals->get('SLOGAN', '') ?? '');
        $baseUrl = (string) ($this->globals->get('BASEURL', '') ?? '');
        $dateFounded = (string) ($this->globals->get('datefounded', '') ?? '');
        $projectName = (string) ($this->globals->get('PROJECTNAME', '') ?? '');

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
        if (! preg_match(':^/(\d{1,10})/([\w]{32,64})/(.+)$:', $pathInfo, $matches)) {
            abort(404);
        }

        $id = (int) $matches[1];
        $token = $matches[2];
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

        if (! $this->secureTokenService->verifyEmailChangeToken((string) $user->editsecret, $email, $token)) {
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

        $title = match ($type) {
            'adminactivate', 'inviter', 'signup' => __('legacy/ok.head_user_signup'),
            'sysop' => __('legacy/ok.head_sysop_activation'),
            'confirmed' => __('legacy/ok.head_already_confirmed'),
            'confirm' => __('legacy/ok.head_signup_confirmation'),
            default => '',
        };

        return $this->legacyPage($request, 'ok', false, [
            'type' => $type,
            'email' => $email,
            'title' => $title,
            'siteName' => Setting::getSiteName(),
            'CURUSER' => $this->currentUser->get(),
        ]);
    }
}
