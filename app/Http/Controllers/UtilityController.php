<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\AttachmentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\UserFontsize;
use App\Enums\UserTheme;
use App\Http\Requests\AttachmentUploadRequest;
use App\Http\Requests\PreviewRequest;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Repositories\SearchPageRepository;
use App\Repositories\TorrentListingRepository;
use App\Repositories\UsercpSecurityCommand;
use App\Services\AttachmentMutationService;
use App\Services\SecureTokenService;
use App\Services\UsersearchPageService;
use App\Support\Api;
use App\Support\Attachment\AttachmentService;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Captcha;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\LegacyAjaxRedirects;
use App\Support\LegacyAuth;
use App\Support\LegacyHeaderBag;
use App\Support\Logger;
use App\Support\RedisGuard;
use App\Support\Style;
use App\Support\Url;
use App\ViewModels\TorrentListViewFactory;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UtilityController extends LegacyController
{
    private UsersearchPageService $usersearchPageService;

    private SearchPageRepository $searchPageRepository;

    private CurrentUser $currentUser;

    private ?LegacyRedisCache $legacyRedisCache;

    private LegacyHeaderBag $legacyHeaderBag;

    public function __construct(private readonly AttachmentRepositoryInterface $attachmentRepository, private readonly TorrentListingRepository $torrentListingRepository, private readonly UsercpSecurityCommand $usercpSecurityCommand, private readonly UserRepositoryInterface $userRepository,
        UsersearchPageService $usersearchPageService,
        SearchPageRepository $searchPageRepository,
        CurrentUser $currentUser,
        ?LegacyRedisCache $legacyRedisCache,
        LegacyHeaderBag $legacyHeaderBag,
        private readonly SecureTokenService $secureTokenService,
        private readonly TorrentListViewFactory $torrentListFactory,
    ) {
        $this->usersearchPageService = $usersearchPageService;
        $this->searchPageRepository = $searchPageRepository;
        $this->currentUser = $currentUser;
        $this->legacyRedisCache = $legacyRedisCache;
        $this->legacyHeaderBag = $legacyHeaderBag;
    }

    public function search(Request $request): View|RedirectResponse
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($curUser['id'] ?? 0)) : null;
        if ($currentUser === null) {
            $qs = $request->getQueryString();

            return redirect('/web/search'.($qs ? '?'.$qs : ''));
        }

        $data = $this->searchPageRepository->dataForSearch($request, $currentUser);
        $data['listVm'] = $this->torrentListFactory->create(
            $data['rows'] ?? [],
            (int) SearchBox::getBrowseMode(),
        );

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

            return redirect('/ajax'.($qs ? '?'.$qs : ''));
        }

        $action = (string) $request->input('action', '');

        // Count shim hits per action so the /ajax route can be dropped
        // once this family goes quiet (exposed via /metrics; unmapped
        // actions collapse to __invalid to bound label cardinality).
        $label = LegacyAjaxRedirects::uriFor($action) !== null ? $action : '__invalid';
        RedisGuard::attempt(static function () use ($label) {
            $redis = Redis::connection();
            $redis->incr("metrics:legacy_ajax:{$label}");
            $redis->sadd('metrics:legacy_ajax_actions', $label);
        });

        // The two login-page passkey assertions ran pre-auth in the old
        // dispatcher — their REST endpoints are guest-facing too.
        $guestActions = ['getPasskeyGetArgs', 'processPasskeyGet'];
        if (! in_array($action, $guestActions, true)) {
            LegacyAuth::requireLoginFromContext();
        }

        // Every action migrated to its own REST endpoint — 308 redirects
        // replay method + body, so legacy {action, params} POSTs land
        // there byte-identically and the target FormRequest flattens
        // the envelope.
        $redirectUri = LegacyAjaxRedirects::uriFor($action);
        if ($redirectUri !== null) {
            return redirect()->to($redirectUri, 308);
        }

        $currentUser = $this->currentUser->get() ?? [];
        Logger::writeWithContext((string) ('hacking attempt made by '.($currentUser['username'] ?? 'guest').',uid '.($currentUser['id'] ?? 0)), (string) 'error', (bool) false);

        return response()->json(Api::call(1, "Invalid action: {$action}", $request->only(['action', 'params'])));
    }

    public function attachment(Request $request): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($currentUser['id'] ?? 0));

        return $this->renderAttachment($request, $currentUser, $Attach);
    }

    public function attachmentStore(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/attachments/upload'.$suffix, 308);
    }

    public function attachmentUpload(AttachmentUploadRequest $request, AttachmentMutationService $attachmentMutationService): Response
    {
        $currentUser = $this->currentUser->get() ?? [];
        $Attach = new AttachmentService((int) ($currentUser['id'] ?? 0));

        $warning = '';
        $script = '';
        $countLeft = null;

        if ($Attach->enable_attachment()) {
            $uploaded = $request->file('file');
            if ($uploaded instanceof UploadedFile) {
                $uploaded = [$uploaded];
            }
            $uploaded = is_array($uploaded) ? $uploaded : [];

            $altsize = (string) $request->input('altsize', '');
            $callbackFunc = (string) $request->input('callback_func', '');

            $warnings = [];
            foreach ($uploaded as $item) {
                if (! $item instanceof UploadedFile) {
                    continue;
                }
                $file = [
                    'tmp_name' => $item->getPathname(),
                    'size' => $item->getSize(),
                    'type' => $item->getMimeType(),
                    'name' => $item->getClientOriginalName(),
                ];
                $result = $attachmentMutationService->processUpload($currentUser, $Attach, $altsize, $callbackFunc, $file);
                if (($result['warning'] ?? '') !== '') {
                    $warnings[] = (string) $result['warning'];
                }
                $script .= (string) ($result['script'] ?? '');
                $countLeft = isset($result['count_left']) ? (int) $result['count_left'] : $countLeft;
            }
            if ($uploaded === []) {
                $warnings[] = (string) __('legacy/attachment.text_nothing_received');
            }
            $warning = implode(' ', $warnings);
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
            'theme' => UserTheme::fromStringSafe(is_string($currentUser['theme'] ?? null) ? $currentUser['theme'] : null)->value,
            'fontSize' => UserFontsize::fromMixed($currentUser['fontsize'] ?? null)->stringValue(),
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

        $row = $this->attachmentRepository->findByIdAndDlkey($id, $dlkey) ?? [];
        if ($row === []) {
            return response('No attachment found.', 404, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $httpdirectory = SiteConfig::current()->attachment->httpDirectory();
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

        $this->attachmentRepository->incrementDownloads($id);

        if ($this->legacyRedisCache !== null) {
            $this->legacyRedisCache->delete_value('attachment_'.$dlkey.'_content');
        }

        return new StreamedResponse(function () use ($realFile) {
            $f = fopen($realFile, 'rb');
            if (! $f) {
                return;
            }

            fpassthru($f);
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

        if (! method_exists($driver, 'imageBytes')) {
            return response('Captcha driver does not support image rendering', 404);
        }

        $content = $driver->imageBytes($imagehash);

        // T-11: Read from the per-request LegacyHeaderBag instead of SAPI
        // globals that leak state across Octane worker requests.
        $headerBag = $this->legacyHeaderBag;
        $status = $headerBag->getStatusCode();
        $headers = $headerBag->toResponseHeaders();
        $headerBag->flush();

        $responseStatus = $status !== null && $status >= 100 ? $status : 200;

        return response($content, $responseStatus, $headers);
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
     * @return list<array<string, string|SafeHtml|ViewContract>>
     */
    private function tagItems(string $siteName, string $username): array
    {
        $schemeHost = Url::schemeAndHost(false);
        $t = fn (string $key): string => (string) __('legacy/tags.'.$key);
        $ph = function (string $raw): string|ViewContract {
            $parts = explode('|', $raw);
            if (count($parts) === 1) {
                return $raw;
            }

            return view('tags._ph', ['parts' => $parts]);
        };
        $tag = function (string $name, string $description, string|ViewContract $syntax, string $example, string|ViewContract $remarks = ''): array {
            return [
                'name' => SafeHtml::fromTrustedHtml($name),
                'description' => SafeHtml::fromTrustedHtml($description),
                'syntax' => $syntax instanceof ViewContract ? $syntax : SafeHtml::fromTrustedHtml($syntax),
                'example' => SafeHtml::fromTrustedHtml($example),
                'result' => Format::formatComment($example),
                'remarks' => $remarks instanceof ViewContract ? $remarks : SafeHtml::fromTrustedHtml($remarks),
            ];
        };
        $syntax = fn (string $key): string|ViewContract => $ph($t($key));
        $remarks = fn (string $key): string|ViewContract => $ph($t($key));
        $imageRemarks = fn (string $key): ViewContract => view('tags._image-ext', ['pre' => $t($key)]);

        return [
            $tag($t('text_bold'), $t('text_bold_description'), $syntax('text_bold_syntax'), $t('text_bold_example')),
            $tag($t('text_italic'), $t('text_italic_description'), $syntax('text_italic_syntax'), $t('text_italic_example')),
            $tag($t('text_underline'), $t('text_underline_description'), $syntax('text_underline_syntax'), $t('text_underline_example')),
            $tag($t('text_strikethrough'), $t('text_strikethrough_description'), $syntax('text_strikethrough_syntax'), $t('text_strikethrough_example')),
            $tag($t('text_hide'), $t('text_hide_description'), $syntax('text_hide_syntax'), $t('text_hide_example')),
            $tag($t('text_color_one'), $t('text_color_one_description'), $syntax('text_color_one_syntax'), $t('text_color_one_example'), $remarks('text_color_one_remarks')),
            $tag($t('text_color_two'), $t('text_color_two_description'), $syntax('text_color_two_syntax'), $t('text_color_two_example'), $remarks('text_color_two_remarks')),
            $tag($t('text_size'), $t('text_size_description'), $syntax('text_size_syntax'), $t('text_size_example'), $remarks('text_size_remarks')),
            $tag($t('text_font'), $t('text_font_description'), $syntax('text_font_syntax'), $t('text_font_example'), $remarks('text_font_remarks')),
            $tag($t('text_hyperlink_one'), $t('text_hyperlink_one_description'), $syntax('text_hyperlink_one_syntax'), sprintf($t('text_hyperlink_one_example'), $schemeHost), $remarks('text_hyperlink_one_remarks')),
            $tag($t('text_hyperlink_two'), $t('text_hyperlink_two_description'), $syntax('text_hyperlink_two_syntax'), sprintf($t('text_hyperlink_two_example'), $schemeHost, $siteName), $remarks('text_hyperlink_two_remarks')),
            $tag($t('text_image_one'), $t('text_image_one_description'), $syntax('text_image_one_syntax'), sprintf($t('text_image_one_example'), $schemeHost), $imageRemarks('text_image_one_remarks')),
            $tag($t('text_image_two'), $t('text_image_two_description'), $syntax('text_image_two_syntax'), sprintf($t('text_image_two_example'), $schemeHost), $imageRemarks('text_image_two_remarks')),
            $tag($t('text_quote_one'), $t('text_quote_one_description'), $syntax('text_quote_one_syntax'), sprintf($t('text_quote_one_example'), $siteName)),
            $tag($t('text_quote_two'), $t('text_quote_two_description'), $syntax('text_quote_two_syntax'), sprintf($t('text_quote_two_example'), $username, $siteName)),
            $tag($t('text_list'), $t('text_description'), $syntax('text_list_syntax'), $t('text_list_example')),
            $tag($t('text_preformat'), $t('text_preformat_description'), $syntax('text_preformat_syntax'), $t('text_preformat_example')),
            $tag($t('text_code'), $t('text_code_description'), $syntax('text_code_syntax'), $t('text_code_example')),
            $tag($t('text_site'), $t('text_site_description'), $syntax('text_site_syntax'), $t('text_site_example')),
            $tag($t('text_siteurl'), $t('text_siteurl_description'), $syntax('text_siteurl_syntax'), $t('text_siteurl_example')),
            $tag($t('text_left'), $t('text_left_description'), $syntax('text_left_syntax'), $t('text_left_example')),
            $tag($t('text_center'), $t('text_center_description'), $syntax('text_center_syntax'), $t('text_center_example')),
            $tag($t('text_right'), $t('text_right_description'), $syntax('text_right_syntax'), $t('text_right_example')),
            $tag($t('text_youtube'), $t('text_youtube_description'), $syntax('text_youtube_syntax'), $t('text_youtube_example')),
            $tag($t('text_spoiler'), $t('text_spoiler_description'), $syntax('text_spoiler_syntax'), $t('text_spoiler_example')),
            $tag($t('text_hr'), $t('text_hr_description'), $syntax('text_hr_syntax'), $t('text_hr_example')),
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

        $suggestRows = $this->torrentListingRepository->suggestKeywords($q);

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
        return $this->renderPreview($request);
    }

    public function previewSubmit(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/preview'.$suffix, 308);
    }

    public function previewRender(PreviewRequest $request): View|RedirectResponse
    {
        return $this->renderPreview($request);
    }

    private function renderPreview(Request $request): View|RedirectResponse
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

        return $this->legacyPage($request, 'smilies', true);
    }

    public function opensearch(Request $request): Response
    {
        $xml = RedisGuard::remember('opensearch_description', 86400, function () {
            return $this->buildOpensearchXml();
        });

        return response((string) $xml, 200, ['Content-Type' => 'text/xml']);
    }

    private function buildOpensearchXml(): string
    {
        $siteName = SiteConfig::current()->basic->siteName();
        $siteEmail = SiteConfig::current()->main->siteEmail();
        $slogan = SiteConfig::current()->main->slogan();
        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: Input::serverValue('HTTP_HOST', 'localhost');
        $dateFounded = SiteConfig::current()->tweak->dateFounded();
        $projectName = PROJECTNAME;

        $url = Url::absolute($baseUrl);
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
              template="{$url}/web/torrents?search={searchTerms}&amp;page={startPage?}" />
    <Url type="application/rss+xml"
        rel="results"
        indexOffset="0"
        template="{$url}/web/torrentrss?search={searchTerms}&amp;rows={count?}&amp;startindex={startIndex?}" />
    <Url type="application/opensearchdescription+xml"
        rel="self"
        template="{$url}/web/opensearch" />
    <Url type="application/x-suggestions+json"
        rel="suggestions"
        template="{$url}/web/searchsuggest?q={searchTerms}" />
    <Contact>{$siteEmail}</Contact>
    <Tags>Torrents {$projectName}</Tags>
    <LongName>{$siteNameEsc} Torrents Search</LongName>
    <Image height="32" width="32" type="image/x-icon">{$faviconData}</Image>
    <Image height="32" width="32" type="image/x-icon">{$url}/favicon.ico</Image>
    <moz:SearchForm>{$url}/web/torrents</moz:SearchForm>
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

        $user = $this->userRepository->findById($id, ['editsecret']);
        if (! $user) {
            abort(404);
        }

        if (! $this->secureTokenService->verifyEmailChangeToken((string) $user->editsecret, $email, $token)) {
            abort(404);
        }

        $affected = $this->usercpSecurityCommand->applyEmailChange($id, (string) $user->editsecret, $email);
        if (! $affected) {
            abort(404);
        }

        return redirect('/usercp?action=security&type=saved');
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
