<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\GetrssRequest;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Locale;
use App\Support\PageResponses;
use App\Support\RequestValues;
use App\Support\SearchBox;
use App\Support\Url;
use App\ViewModels\Search\SearchCategoryTableFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class RssController extends LegacyController
{
    public function __construct(
        private readonly CurrentUser $currentUser,
        private readonly SearchCategoryTableFactory $searchCategoryTableFactory,
    ) {}

    public function getrss(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            return redirect('/web/getrss');
        }

        return $this->renderPage($request, 'getrss', true, $this->getrssData());
    }

    public function getrssPost(Request $request): RedirectResponse
    {
        // Renamed endpoint — 308 replays the body + query string unchanged.
        $qs = $request->getQueryString();
        $suffix = $qs !== null && $qs !== '' ? '?'.$qs : '';

        return redirect()->to('/web/rss/generate'.$suffix, 308);
    }

    public function getrssSubmit(GetrssRequest $request): Response|RedirectResponse
    {
        return $this->handleGetrssPost($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function getrssData(): array
    {
        $browsecatmode = SiteConfig::current()->main->browseCat(1);
        $brsectiontype = $browsecatmode;

        $showsubcat = (bool) SearchBox::valueWithContext($brsectiontype, 'showsubcat');
        $showsource = (bool) SearchBox::valueWithContext($brsectiontype, 'showsource');
        $showmedium = (bool) SearchBox::valueWithContext($brsectiontype, 'showmedium');
        $showcodec = (bool) SearchBox::valueWithContext($brsectiontype, 'showcodec');
        $showstandard = (bool) SearchBox::valueWithContext($brsectiontype, 'showstandard');
        $showprocessing = (bool) SearchBox::valueWithContext($brsectiontype, 'showprocessing');
        $showaudiocodec = (bool) SearchBox::valueWithContext($brsectiontype, 'showaudiocodec');
        $catsperrow = (int) SearchBox::valueWithContext($brsectiontype, 'catsperrow');
        $catpadding = SearchBox::valueWithContext($brsectiontype, 'catpadding');

        $brcats = Category::listByModeWithContext($brsectiontype);

        $data = compact(
            'browsecatmode',
            'brsectiontype',
            'showsubcat',
            'showsource',
            'showmedium',
            'showcodec',
            'showstandard',
            'showprocessing',
            'showaudiocodec',
            'catsperrow',
            'catpadding',
            'brcats'
        );

        if ($showsubcat) {
            if ($showsource) {
                $data['sources'] = SearchBox::itemListWithContext('sources', $brsectiontype);
            }
            if ($showmedium) {
                $data['media'] = SearchBox::itemListWithContext('media', $brsectiontype);
            }
            if ($showcodec) {
                $data['codecs'] = SearchBox::itemListWithContext('codecs', $brsectiontype);
            }
            if ($showstandard) {
                $data['standards'] = SearchBox::itemListWithContext('standards', $brsectiontype);
            }
            if ($showprocessing) {
                $data['processings'] = SearchBox::itemListWithContext('processings', $brsectiontype);
            }
            if ($showaudiocodec) {
                $data['audiocodecs'] = SearchBox::itemListWithContext('audiocodecs', $brsectiontype);
            }
        }

        $data['categoriesTable'] = $this->searchCategoryTableFactory->create($browsecatmode, 'yes', '/web/torrents?allsec=1&', '', 3, '', ['section_name' => true]);
        $data['paidTorrentEnabled'] = SiteConfig::current()->torrent->paidTorrentEnabled();
        $data['allowed_showrows'] = ['10', '50'];
        $data['stickyTypes'] = [
            0 => Locale::trans('torrent.pos_state_normal', [], null),
            1 => Locale::trans('torrent.pos_state_sticky', [], null),
            2 => Locale::trans('torrent.pos_state_r_sticky', [], null),
        ];

        return $data;
    }

    private function handleGetrssPost(Request $request): Response|RedirectResponse
    {
        $curUser = $this->currentUser->get();
        if ($curUser === null) {
            return redirect('/web/getrss');
        }

        $browsecatmode = SiteConfig::current()->main->browseCat(1);
        $baseUrl = SiteConfig::current()->basic->baseUrl() ?: RequestValues::serverValue('HTTP_HOST', 'localhost');

        $allowedShowrows = ['10', '50'];
        $showrows = (string) $request->input('showrows', '10');
        if (! in_array($showrows, $allowedShowrows, true)) {
            return $this->getrssMessageResponse(__('getrss.std_error'), __('getrss.std_no_row'));
        }

        $query = ['passkey' => $this->currentUser->passkey(), 'rows' => (int) $showrows];

        $brcats = Category::listByModeWithContext($browsecatmode);
        foreach ($brcats as $cat) {
            if ($request->filled('cat'.$cat['id'])) {
                $query['cat'.$cat['id']] = 1;
            }
        }

        if (SearchBox::valueWithContext($browsecatmode, 'showsubcat')) {
            $subcatMap = [
                'showsource' => 'sources',
                'showmedium' => 'media',
                'showcodec' => 'codecs',
                'showstandard' => 'standards',
                'showprocessing' => 'processings',
                'showaudiocodec' => 'audiocodecs',
            ];
            foreach ($subcatMap as $flag => $table) {
                if (! SearchBox::valueWithContext($browsecatmode, $flag)) {
                    continue;
                }
                $subcatKeyMap = [
                    'sources' => 'sou',
                    'media' => 'med',
                    'codecs' => 'cod',
                    'standards' => 'sta',
                    'processings' => 'pro',
                    'audiocodecs' => 'aud',
                ];
                $key = $subcatKeyMap[$table];
                foreach (SearchBox::itemListWithContext($table, $browsecatmode) as $item) {
                    if ($request->filled($key.$item['id'])) {
                        $query[$key.$item['id']] = 1;
                    }
                }
            }
        }

        if ($request->filled('itemcategory')) {
            $query['icat'] = 1;
        }
        if ($request->filled('itemsmalldescr')) {
            $query['ismalldescr'] = 1;
        }
        if ($request->filled('itemsize')) {
            $query['isize'] = 1;
        }
        if ($request->filled('itemuploader')) {
            $query['iuplder'] = 1;
        }

        $searchstr = trim((string) $request->input('search', ''));
        if ($searchstr !== '') {
            $query['search'] = rawurlencode($searchstr);
            if ($request->filled('search_mode')) {
                $searchMode = (int) $request->input('search_mode');
                if (! in_array($searchMode, [0, 2], true)) {
                    $searchMode = 0;
                }
                $query['search_mode'] = $searchMode;
            }
        }

        $sticky = $request->input('sticky');
        if (is_array($sticky) && ! empty($sticky)) {
            $query['sticky'] = implode(',', array_map('intval', $sticky));
        }

        if ($request->filled('paid')) {
            $query['paid'] = (int) $request->input('paid');
        }

        $inclbookmarked = (int) $request->input('inclbookmarked', 0);
        $addinclbm = '';
        if (in_array($inclbookmarked, [0, 1], true)) {
            $addinclbm = '&inclbookmarked='.$inclbookmarked;
        }

        $link = Url::absolute($baseUrl).'/web/torrentrss?'.http_build_query($query).$addinclbm;
        $msg = (__('getrss.std_use_following_url'))."\n".$link."\n\n"
            .(__('getrss.std_utorrent_feed_url'))."\n".$link.'&linktype=dl'.$addinclbm;

        return $this->getrssMessageResponse(__('getrss.std_done'), (string) Format::formatComment($msg), __('getrss.head_rss_feeds'));
    }

    private function getrssMessageResponse(string $heading, string $text, string $title = ''): Response
    {
        return response(PageResponses::captureAbort($heading, $text, false, $title));
    }
}
