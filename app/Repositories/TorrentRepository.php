<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\BookmarkFilter;
use App\Enums\TorrentVisible;
use App\Exceptions\NexusException;
use App\Http\Resources\TorrentResource;
use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Codec;
use App\Models\Media;
use App\Models\Processing;
use App\Models\SearchBox;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Torrent;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\Description;
use App\Support\Locale;
use App\Support\Logger;
use App\Support\Torrent\TorrentStatus;
use App\Utils\ApiQueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Torrent repository: listing, detail, peer/snatch, and presentation helpers.
 *
 * Purchase, download, and moderation logic has been extracted to:
 *
 * @see TorrentPurchaseRepository
 * @see TorrentDownloadRepository
 * @see TorrentModerationRepository
 */
class TorrentRepository extends BaseRepository implements TorrentRepositoryInterface
{
    public function __construct(
        private readonly TorrentDownloadRepositoryInterface $downloadRepository,
    ) {}

    /** @var array<int, string> */
    private static array $defaultLoadRelationships = [
        'basic_category', 'basic_category.search_box',
        'basic_audiocodec', 'basic_codec', 'basic_medium',
        'basic_source', 'basic_processing', 'basic_standard', ];

    /** @var array<int, string> */
    private static array $allowIncludes = ['user', 'extra', 'tags'];

    /** @var array<int, string> */
    private static array $allowIncludeCounts = ['thank_users', 'reward_logs'];

    /** @var array<int, string> */
    private static array $allowIncludeFields = [
        'has_bookmarked', 'has_thanked', 'has_rewarded',
        'description', 'download_url', 'active_status',
    ];

    /** @var array<int, string> */
    private static array $allowFilters = [
        'title', 'category', 'source', 'medium', 'codec', 'audiocodec', 'standard', 'processing',
        'owner', 'visible', 'added', 'size', 'sp_state', 'leechers', 'seeders', 'times_completed',
        'bookmark',
    ];

    /** @var array<int, string> */
    private static array $allowSorts = ['id', 'comments', 'size', 'seeders', 'leechers', 'times_completed'];

    /**
     * fetch torrent list
     *
     * @return mixed
     */
    public function getList(Request $request, User $user, ?string $sectionName = null)
    {
        if (empty($sectionName)) {
            $sectionId = (int) SearchBox::getBrowseMode();
            $searchBox = SearchBox::query()->find($sectionId);
        } else {
            $searchBox = SearchBox::query()->where('name', $sectionName)->first();
        }
        if (! $searchBox instanceof SearchBox) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        $categoryIdList = $searchBox->categories()->pluck('id')->toArray();
        // query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships)
            ->whereIn('category', $categoryIdList)
            ->orderBy('pos_state', 'desc');
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query, $request)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields)
            ->allowFilters(self::$allowFilters)
            ->allowSorts(self::$allowSorts)
            ->registerCustomFilter('title', function (Builder $query, Request $request) {
                $title = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.title');
                $title = trim(str_replace('.', ' ', (string) $title));
                if ($title) {
                    $titleParts = explode(' ', $title);
                    $keywordCount = 1;
                    foreach ($titleParts as $titlePart) {
                        if ($keywordCount > 3) {
                            break;
                        }
                        $titlePart = trim($titlePart);
                        $query->where(function (Builder $query) use ($titlePart) {
                            $query->where('name', 'like', '%'.$titlePart.'%');
                        });
                        $keywordCount++;
                    }
                }
            })
            ->registerCustomFilter('bookmark', function (Builder $query, Request $request) use ($user) {
                $filterBookmark = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.bookmark');
                if ($filterBookmark === BookmarkFilter::INCLUDE->value) {
                    $query->whereHas('bookmarks', function (Builder $query) use ($user) {
                        $query->where('userid', $user->id);
                    });
                } elseif ($filterBookmark === BookmarkFilter::EXCLUDE->value) {
                    $query->whereDoesntHave('bookmarks', function (Builder $query) use ($user) {
                        $query->where('userid', $user->id);
                    });
                }
            })
            ->registerCustomFilter('visible', function (Builder $query, Request $request) {
                $filterVisible = $request->input(ApiQueryBuilder::PARAM_NAME_FILTER.'.visible', Torrent::FILTER_VISIBLE_YES);
                if ($filterVisible === Torrent::FILTER_VISIBLE_YES) {
                    $query->where('visible', TorrentVisible::YES->value);
                } elseif ($filterVisible === Torrent::FILTER_VISIBLE_NO) {
                    $query->where('visible', TorrentVisible::NO->value);
                }
            });
        $query = $apiQueryBuilder->build();
        if (! $apiQueryBuilder->hasSort() || ! $apiQueryBuilder->hasSort('id')) {
            $query->orderBy('id', 'desc');
        }
        Logger::writeWithContext((string) 'before query torrent list', (string) 'info', (bool) false);
        $torrents = $query->paginate($this->getPerPageFromRequest($request));
        Logger::writeWithContext((string) 'after query torrent list', (string) 'info', (bool) false);

        return $this->appendIncludeFields($apiQueryBuilder, $user, $torrents);
    }

    /**
     * @return mixed
     */
    public function getDetail(int $id, User $user)
    {
        // query this info default
        $query = Torrent::query()->with(self::$defaultLoadRelationships);
        $apiQueryBuilder = ApiQueryBuilder::for(TorrentResource::NAME, $query)
            ->allowIncludes(self::$allowIncludes)
            ->allowIncludeCounts(self::$allowIncludeCounts)
            ->allowIncludeFields(self::$allowIncludeFields);
        Logger::writeWithContext((string) 'before query torrent detail', (string) 'info', (bool) false);
        $torrent = $apiQueryBuilder->build()->findOrFail($id);
        Logger::writeWithContext((string) 'before query torrent detail', (string) 'info', (bool) false);
        $torrentList = $this->appendIncludeFields($apiQueryBuilder, $user, [$torrent]);

        return $torrentList[0];
    }

    /**
     * @param  mixed  $torrentList
     * @return mixed
     */
    private function appendIncludeFields(ApiQueryBuilder $apiQueryBuilder, User $user, $torrentList)
    {
        $torrentIdArr = $bookmarkData = $thankData = $rewardData = $activeData = [];
        foreach ($torrentList as $torrent) {
            $torrentIdArr[] = $torrent->id;
        }
        unset($torrent);
        if ($hasFieldHasBookmarked = $apiQueryBuilder->hasIncludeField('has_bookmarked')) {
            $bookmarkData = $user->bookmarks()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldHasThanked = $apiQueryBuilder->hasIncludeField('has_thanked')) {
            $thankData = $user->thank_torrent_logs()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldHasRewarded = $apiQueryBuilder->hasIncludeField('has_rewarded')) {
            $rewardData = $user->reward_torrent_logs()->whereIn('torrentid', $torrentIdArr)->get()->keyBy('torrentid');
        }
        if ($hasFieldActiveStatus = $apiQueryBuilder->hasIncludeField('active_status')) {
            $torrentModule = new TorrentStatus;
            $activeData = $torrentModule->listLeechingSeedingStatus($user->id, $torrentIdArr);
        }
        Logger::writeWithContext((string) 'after prepare has data', (string) 'info', (bool) false);

        $downloadRepo = $this->downloadRepository;
        foreach ($torrentList as $torrent) {
            $id = $torrent->id;
            if ($hasFieldHasBookmarked) {
                $torrent->has_bookmarked = $bookmarkData->has($id);
            }
            if ($hasFieldHasThanked) {
                $torrent->has_thanked = $thankData->has($id);
            }
            if ($hasFieldHasRewarded) {
                $torrent->has_rewarded = $rewardData->has($id);
            }
            if ($hasFieldActiveStatus) {
                $torrent->active_status = $activeData[$id] ?? null;
            }

            if ($apiQueryBuilder->hasIncludeField('description') && $apiQueryBuilder->hasInclude('extra')) {
                $descriptionArr = Description::parse($torrent->extra->descr ?? '');
                $torrent->description = $descriptionArr;
                $torrent->images = Description::imageFromDescription($descriptionArr);
            }
            if ($apiQueryBuilder->hasIncludeField('download_url')) {
                $torrent->download_url = $downloadRepo->getDownloadUrl($id, $user);
            }
        }
        Logger::writeWithContext((string) 'after fill has data', (string) 'info', (bool) false);

        return $torrentList;
    }

    /**
     * @return mixed
     */
    public function getSearchBox(?int $id = null)
    {
        if ($id === null) {
            $id = SiteConfig::current()->main->browseCat();
        }
        $searchBox = SearchBox::query()->findOrFail((int) $id);
        $category = $searchBox->categories()->orderBy('sort_index')->orderBy('id')->get();
        $modalRows = [];
        $modalRows[] = $categoryFormatted = $this->formatRow(Category::getLabelName(), $category, 'category');
        if ($searchBox->showsubcat) {
            if ($searchBox->showsource) {
                $source = Source::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Source::getLabelName(), $source, 'source');
            }
            if ($searchBox->showmedium) {
                $media = Media::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Media::getLabelName(), $media, 'medium');
            }
            if ($searchBox->showcodec) {
                $codec = Codec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Codec::getLabelName(), $codec, 'codec');
            }
            if ($searchBox->showstandard) {
                $standard = Standard::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Standard::getLabelName(), $standard, 'standard');
            }
            if ($searchBox->showprocessing) {
                $processing = Processing::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Processing::getLabelName(), $processing, 'processing');
            }
            if ($searchBox->showaudiocodec) {
                $audioCodec = AudioCodec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(AudioCodec::getLabelName(), $audioCodec, 'audio_codec');
            }
        }
        $results = [];
        $categories = $categoryFormatted['rows'];
        $categories[0]['active'] = 1;
        $results['categories'] = $categories;
        $results['modal_rows'] = $modalRows;

        return $results;
    }

    /**
     * @param  mixed  $header
     * @param  mixed  $items
     * @param  mixed  $name
     * @return mixed
     */
    private function formatRow($header, $items, $name)
    {
        $result['header'] = $header;
        $result['rows'][] = [
            'label' => 'All',
            'value' => 0,
            'name' => $name,
            'active' => 1,
        ];
        foreach ($items as $value) {
            $item = [
                'label' => $value->name,
                'value' => $value->id,
                'name' => $name,
                'active' => 0,
            ];
            $result['rows'][] = $item;
        }

        return $result;
    }

    /**
     * @param  array<int|string, mixed>  $torrentInfo
     * @param  mixed  $size
     * @param  mixed  $verticalAlign
     * @return mixed
     */
    public function getPaidIcon(array $torrentInfo, $size = 16, $verticalAlign = 'sub')
    {
        if (! isset($torrentInfo['price']) || $torrentInfo['price'] <= 0) {
            return '';
        }

        return sprintf('<span title="%s" style="vertical-align: %s"><svg t="1676058062789" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="3406" width="%s" height="%s"><path d="M554.666667 810.666667v42.666666h-85.333334v-42.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667h85.333333c0 46.933333 38.4 85.333333 85.333333 85.333333v-170.666666c-93.866667 0-170.666667-76.8-170.666666-170.666667s76.8-170.666667 170.666666-170.666667V170.666667h85.333334v42.666666c93.866667 0 170.666667 76.8 170.666666 170.666667h-85.333333c0-46.933333-38.4-85.333333-85.333333-85.333333v170.666666h17.066666c29.866667 0 68.266667 17.066667 98.133334 42.666667 34.133333 29.866667 59.733333 76.8 59.733333 128-4.266667 93.866667-81.066667 170.666667-174.933333 170.666667z m0-85.333334c46.933333 0 85.333333-38.4 85.333333-85.333333s-38.4-85.333333-85.333333-85.333333v170.666666zM469.333333 298.666667c-46.933333 0-85.333333 38.4-85.333333 85.333333s38.4 85.333333 85.333333 85.333333V298.666667z" fill="#CD7F32" p-id="3407"></path></svg></span>', Locale::trans('torrent.paid_torrent', [], null), $verticalAlign, $size, $size);
    }

    /**
     * @param  mixed  $name
     * @param  mixed  $value
     * @param  mixed  $noteText
     * @param  mixed  $btnText
     * @param  mixed  $btnId
     * @param  mixed  $btnOnClick
     */
    public function buildUploadFieldInput($name, $value, $noteText, $btnText, $btnId = '', $btnOnClick = ''): string
    {
        $btn = $note = '';
        if ($btnText) {
            $idAttr = $btnId ? ' id="'.htmlspecialchars($btnId, ENT_QUOTES, 'UTF-8').'"' : '';
            $onClickAttr = $btnOnClick ? ' onclick="'.htmlspecialchars($btnOnClick, ENT_QUOTES, 'UTF-8').'"' : '';
            $btn = '<div><input type="button" class="nexus-action-btn" value="'.$btnText.'"'.$idAttr.$onClickAttr.'></div>';
        }
        if ($noteText) {
            $note = '<span class="medium">'.$noteText.'</span>';
        }
        $input = <<<HTML
<div class="nexus-input-box" style="display: flex">
    <div style="display: flex;flex-direction: column;flex-grow: 1">
        <input type="text" id="$name" name="$name" value="{$value}">
        $note
    </div>
    $btn
</div>
HTML;

        return $input;
    }
}
