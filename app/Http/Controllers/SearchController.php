<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Repositories\SearchPageRepository;
use App\Repositories\TorrentListingRepository;
use App\Services\UsersearchPageService;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Input;
use App\Support\RedisGuard;
use App\Support\Url;
use App\ViewModels\TorrentListViewFactory;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SearchController extends LegacyController
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UsersearchPageService $usersearchPageService,
        private readonly SearchPageRepository $searchPageRepository,
        private readonly CurrentUser $currentUser,
        private readonly TorrentListViewFactory $torrentListFactory,
        private readonly TorrentListingRepository $torrentListingRepository,
    ) {}

    public function search(Request $request): View|RedirectResponse
    {
        $curUser = $this->currentUser->get() ?? [];
        $currentUser = ! empty($curUser) ? $this->userRepository->findById((int) ($this->currentUser->id())) : null;
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
        $t = fn (string $key): string => (string) __('tags.'.$key);
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
}
