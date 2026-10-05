<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentDownloadRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\TorrentApprovalStatus;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentPurchaseRepository;
use App\Support\AssetAppender;
use App\Support\Cache\LegacyRedisCache;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyYesNo;
use App\Support\Logger;
use App\Support\Pagination;
use App\Support\Strings;
use App\Support\Torrent\BdInfoExtra;
use App\Support\Torrent\TechnicalInformation;
use App\ViewModels\Torrent\TorrentDetailsViewFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TorrentDetailsController extends Controller
{
    private TorrentDownloadRepositoryInterface $downloadRepository;

    private TagRepositoryInterface $tagRepository;

    private TorrentDetailRepository $torrentDetailRepository;

    private CurrentUser $currentUser;

    private TorrentDetailsViewFactory $detailsViewFactory;

    private ?LegacyRedisCache $legacyRedisCache;

    public function __construct(private readonly TorrentPurchaseRepository $torrentPurchaseRepository, private readonly TorrentRepositoryInterface $torrentRepository,
        TorrentDownloadRepositoryInterface $downloadRepository,
        TagRepositoryInterface $tagRepository,
        TorrentDetailRepository $torrentDetailRepository,
        CurrentUser $currentUser,
        TorrentDetailsViewFactory $detailsViewFactory,
        ?LegacyRedisCache $legacyRedisCache = null
    ) {
        $this->downloadRepository = $downloadRepository;
        $this->tagRepository = $tagRepository;
        $this->torrentDetailRepository = $torrentDetailRepository;
        $this->currentUser = $currentUser;
        $this->detailsViewFactory = $detailsViewFactory;
        $this->legacyRedisCache = $legacyRedisCache;
    }

    public function show(Request $request, int $id, CustomField $customField): View|RedirectResponse|Response
    {
        if ($id <= 0) {
            abort(404);
        }

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $torrent = $this->torrentRepository->findById($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        Gate::forUser($user)->authorize('view', $torrent);

        if ($this->legacyRedisCache === null) {
            $query = $request->query->all();
            unset($query['id']);

            return redirect('/details.php?id='.$id.($query ? '&'.http_build_query($query) : ''));
        }

        $row = $this->torrentDetailRepository->getTorrent($id);
        if (empty($row)) {
            Logger::writeWithContext((string) "TorrentDetailsRepository getTorrent empty: {$id}", (string) 'info', (bool) false);
            abort(404);
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        $headTitle = empty($request->input('cmtpage'))
            ? (__('legacy/details.head_details_for_torrent')).'"'.$row['name'].'"'
            : (__('legacy/details.head_comments_for_torrent')).'"'.$row['name'].'"';

        $denyLog = $row['approval_status'] == TorrentApprovalStatus::DENY->value
            ? $this->torrentDetailRepository->getLatestApprovalDenyLog($id)
            : null;

        $hasBuy = $this->torrentPurchaseRepository->hasBuySuccess((int) ($currentUser['id'] ?? 0), $id);

        $requestFlags = [
            'hit' => $request->has('hit'),
            'cmtpage' => $request->has('cmtpage'),
            'uploaded' => $request->has('uploaded'),
            'edited' => $request->has('edited'),
            'existed' => $request->has('existed'),
            'returnto' => (string) $request->input('returnto', ''),
            'dllist' => (int) $request->input('dllist', 0) === 1,
        ];

        if ($requestFlags['hit']) {
            $this->torrentDetailRepository->incrementViews($id);
        }

        $headers = [];
        if ($requestFlags['uploaded']) {
            $headers['Refresh'] = "1; url=download.php?id={$id}";
        }

        $tagIds = $this->torrentDetailRepository->getTagIds($id);

        $viewData = $this->buildDetailsViewData($id, $row, $currentUser, $denyLog, $hasBuy, $tagIds, $requestFlags, $customField);

        return response()->view('torrent.details', array_merge([
            'id' => $id,
            'torrentId' => $id,
            'torrentRow' => $row,
            'user' => $user,
            'currentUser' => $currentUser,
            'headTitle' => $headTitle,
            'tagIds' => $tagIds,
            'denyLog' => $denyLog,
            'hasBuy' => $hasBuy,
            'requestFlags' => $requestFlags,
        ], $viewData), 200, $headers);
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<int, int>  $tagIds
     * @param  array<string, mixed>  $requestFlags
     * @return array<string, mixed>
     */
    private function buildDetailsViewData(int $id, array $row, array $currentUser, ?TorrentOperationLog $denyLog, bool $hasBuy, array $tagIds, array $requestFlags, CustomField $customField): array
    {
        $tagHtml = $this->tagRepository->renderSpan((int) ($row['search_box_id'] ?? 0), $tagIds);
        $downloadUrl = $this->downloadRepository->getDownloadUrl($id, $currentUser);
        $customFieldsHtml = $customField->renderOnTorrentDetailsPage($id, (int) ($row['search_box_id'] ?? 0));

        $technicalInfoResult = null;
        if (SiteConfig::current()->main->enableTechnicalInfo() && ! empty($row['technical_info'])) {
            $escaped = Strings::escapeHtml((string) $row['technical_info']);
            $technicalData = is_string($escaped) ? $escaped : '';
            $isBdInfo = false;
            if (! empty($technicalData)) {
                $firstLine = (string) strtok($technicalData, "\n");
                if (
                    str_contains($firstLine, 'DISC INFO')
                    || str_contains($firstLine, 'Disc Title')
                    || str_contains($firstLine, 'Disc Label')
                ) {
                    $isBdInfo = true;
                }
            }

            if ($isBdInfo) {
                $technicalInfo = new BdInfoExtra($technicalData);
            } else {
                $technicalInfo = new TechnicalInformation($technicalData);
            }

            $technicalInfoResult = $technicalInfo->renderOnDetailsPage();
        }

        $rawDescr = (string) ($row['descr'] ?? '');
        $screenshots = [];
        if (preg_match_all('/\[img\](?<url>[^<\[\s]+)\[\/img\]/i', $rawDescr, $m) > 0) {
            foreach (array_unique($m['url']) as $shotUrl) {
                if (count($screenshots) >= 4) {
                    break;
                }
                if (preg_match('/^(https?:)?\/\//i', $shotUrl) === 1 || str_starts_with($shotUrl, '/')) {
                    $screenshots[] = $shotUrl;
                }
            }
            foreach ($screenshots as $shotUrl) {
                $rawDescr = preg_replace('/\[img\]\s*'.preg_quote($shotUrl, '/').'\s*\[\/img\]/i', '', $rawDescr) ?? $rawDescr;
            }
            if (stripos($rawDescr, '[img') === false) {
                $rawDescr = preg_replace('/\[(?:b|u|i|size(?:=[^\]]*)?)\]\s*(?:screenshots?|screen shots?|screens?)\s*:?\s*\[\/(?:b|u|i|size)\]\s*/i', '', $rawDescr) ?? $rawDescr;
            }
        }

        $descr = $rawDescr !== '' ? Format::formatComment($rawDescr) : '';
        $bonusOptions = Setting::getBonusRewardOptions();

        $showDescription = ! LegacyYesNo::isNo($currentUser['showdescription'] ?? null) && $descr !== '';

        $magicInfo = $this->torrentDetailRepository->getMagicInfo($id, (int) $currentUser['id']);
        $details = $this->detailsViewFactory->build(
            $id, $row, $currentUser, $denyLog, $hasBuy, $requestFlags, $magicInfo, $bonusOptions
        );
        $commentPagerTop = '';
        $commentPagerBottom = '';
        $commentCount = 0;
        $commentsEnabled = ! LegacyYesNo::isNo($currentUser['showcomment'] ?? null);
        if ($commentsEnabled) {
            $commentCount = $this->torrentDetailRepository->getCommentCount($id);
            if ($commentCount > 0) {
                [$commentPagerTop, $commentPagerBottom] = Pagination::pager(
                    10, $commentCount, "details.php?id=$id&cmtpage=1&", ['lastpagedefault' => 1], 'page'
                );
            }
        }

        if ($requestFlags['dllist'] ?? false) {
            AssetAppender::js(sprintf('viewpeerlist(%s)', (int) $row['id']), 'footer', false);
        }

        return [
            'details' => $details,
            'tagHtml' => SafeHtml::fromTrustedHtml($tagHtml),
            'downloadUrl' => $downloadUrl,
            'customFieldsHtml' => SafeHtml::fromTrustedHtml($customFieldsHtml),
            'technicalInfoResult' => SafeHtml::fromTrustedHtml((string) ($technicalInfoResult ?? '')),
            'descr' => SafeHtml::fromTrustedHtml($descr),
            'screenshots' => $screenshots,
            'showDescription' => $showDescription,
            'torrentNamePrefix' => SiteConfig::current()->main->torrentNamePrefix(),
            'commentCount' => $commentCount,
            'commentPagerTop' => $commentPagerTop instanceof SafeHtml ? $commentPagerTop : SafeHtml::fromTrustedHtml($commentPagerTop),
            'commentPagerBottom' => $commentPagerBottom instanceof SafeHtml ? $commentPagerBottom : SafeHtml::fromTrustedHtml($commentPagerBottom),
            'commentsEnabled' => $commentsEnabled,
        ];
    }
}
