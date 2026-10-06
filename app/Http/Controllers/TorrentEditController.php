<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\TorrentEditRequest;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\TorrentDetailRepository;
use App\Repositories\TorrentEditRepository;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Format;
use App\Support\Html;
use App\Support\Html\SafeHtml;
use App\Support\Http\SafeReturnUrl;
use App\Support\Locale;
use App\Support\YesNo;
use App\ViewModels\Torrent\TorrentEditPickViewModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TorrentEditController extends Controller
{
    public function __construct(private readonly TorrentRepositoryInterface $torrentRepository,
        private readonly SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        private readonly TagRepositoryInterface $tagRepository,
        private readonly HitAndRunRepository $hitAndRunRepository,
        private readonly CurrentUser $currentUser,
        private readonly TorrentDetailRepository $torrentDetailRepository,
        private readonly CustomField $customField,
    ) {}

    public function legacy(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            $qs = $request->getQueryString();

            return redirect('/edit'.($qs ? '?'.$qs : ''));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            abort(404);
        }

        $torrent = $this->torrentRepository->findById($id);
        if (! $torrent instanceof Torrent) {
            abort(404);
        }

        $row = $this->torrentDetailRepository->getTorrent($id);
        if (empty($row)) {
            abort(404);
        }
        $sectionmode = (int) ($row['search_box_id'] ?? 0);
        $row['cat_mode'] = $sectionmode;

        $currentUser = $this->currentUser->get();
        $this->currentUser->set($currentUser);

        $headTitle = (__('legacy/edit.head_edit_torrent')).'"'.$row['name'].'"';
        $cats = Category::listByModeWithContext($sectionmode);

        $canEdit = (int) ($this->currentUser->id()) === (int) ($row['owner'] ?? 0)
            || Permission::can(PermissionEnum::TORRENT_MANAGE);

        $priceRow = null;
        if (Permission::can(PermissionEnum::TORRENT_SET_PRICE) && SiteConfig::current()->torrent->paidTorrentEnabled()) {
            $maxPrice = SiteConfig::current()->torrent->maxPrice();
            $priceRow = [
                'value' => (string) $row['price'],
                'placeholder' => $maxPrice > 0
                    ? Locale::trans('label.torrent.max_price_help', ['max_price' => $maxPrice], null)
                    : '',
                'help' => Locale::trans('label.torrent.price_help', ['tax_factor' => SiteConfig::current()->torrent->taxFactor() * 100 .'%'], null),
            ];
        }

        $showVisibleCheck = Permission::can(PermissionEnum::TORRENT_MANAGE);
        $showAnonymousCheck = Permission::can(PermissionEnum::BE_ANONYMOUS) || $showVisibleCheck;

        $pick = null;
        if (
            Permission::can(PermissionEnum::TORRENT_SET_STICKY)
            || (Permission::can(PermissionEnum::TORRENT_MANAGE) && $this->currentUser->yes('picker'))
        ) {
            $promotionOptions = Permission::can(PermissionEnum::TORRENT_ON_PROMOTION)
                ? SafeHtml::fromTrustedHtml(Html::promotionSelection((int) $row['sp_state'], 0))
                : null;

            $posStates = null;
            if (Permission::can(PermissionEnum::TORRENT_SET_STICKY)) {
                $posStates = [];
                foreach (Torrent::listPosStates() as $key => $value) {
                    $posStates[] = ['key' => (string) $key, 'text' => $value['text']];
                }
            }

            $addedTimeStamp = strtotime((string) $row['added']);
            $promotionDurations = [];
            foreach ([900, 1800, 3600, 5400, 7200, 14400, 21600, 28800, 43200, 64800, 86400, 129600, 259200, 604800, 1296000, 2592000, 7776000, 15552000, 31104000] as $seconds) {
                $promotionDurations[] = [
                    'value' => date('Y-m-d H:i:s', $addedTimeStamp + $seconds),
                    'label' => Format::prettyTimeWithLocale($seconds),
                ];
            }

            $pick = new TorrentEditPickViewModel(
                promotionOptions: $promotionOptions,
                promotionTimeType: (int) $row['promotion_time_type'],
                promotionUntil: $row['promotion_until'] > $row['added'] ? (string) $row['promotion_until'] : '',
                promotionDurations: $promotionDurations,
                posStates: $posStates,
                posStateSelected: (string) $row['pos_state'],
                posStateUntil: (string) $row['pos_state_until'],
                specialLabel: (string) (__('legacy/edit.row_special_torrent')),
                positionLabel: html_entity_decode((string) (__('legacy/edit.row_torrent_position')), ENT_QUOTES | ENT_HTML401, 'UTF-8'),
                deadlineLabel: SafeHtml::fromTrustedHtml(Locale::trans('label.deadline', [], null).'&nbsp;'),
            );
        }

        $showDeleteForm = Permission::can(PermissionEnum::TORRENT_DELETE) && Permission::can(PermissionEnum::TORRENT_MANAGE);

        return view('torrent.edit', [
            'torrentId' => $id,
            'torrentRow' => $row,
            'currentUser' => $currentUser,
            'headTitle' => $headTitle,
            'tagIds' => $this->torrentDetailRepository->getTagIds($id),
            'cats' => $cats,
            'returnto' => (string) $request->input('returnto', ''),
            'requestUri' => is_string($request->server('REQUEST_URI')) ? $request->server('REQUEST_URI') : '',
            'taxonomySelect' => SafeHtml::fromTrustedHtml($this->searchBoxSchemaBuilder->renderTaxonomySelect($sectionmode, $row)),
            'tagCheckbox' => SafeHtml::fromTrustedHtml($this->tagRepository->renderCheckbox($sectionmode, (array) $this->torrentDetailRepository->getTagIds($id))),
            'customFieldsHtml' => SafeHtml::fromTrustedHtml((string) $this->customField->renderOnUploadPage($id, $sectionmode)),
            'hitAndRunHtml' => SafeHtml::fromTrustedHtml((string) $this->hitAndRunRepository->renderOnUploadPage($row['hr'] ?? 0, $sectionmode)),
            'canEdit' => $canEdit,
            'priceRow' => $priceRow,
            'sectionMode' => $sectionmode,
            'showVisibleCheck' => $showVisibleCheck,
            'visibleChecked' => YesNo::isYes($row['visible'] ?? null),
            'showAnonymousCheck' => $showAnonymousCheck,
            'anonymousChecked' => YesNo::isYes($row['anonymous'] ?? null),
            'pick' => $pick,
            'showDeleteForm' => $showDeleteForm,
            'descrContent' => (string) ($row['descr'] ?? ''),
            'technicalInfoEnabled' => SiteConfig::current()->main->enableTechnicalInfo(),
            'modeClass' => 'mode_'.$sectionmode,
        ]);
    }

    public function legacyUpdate(TorrentEditRequest $request, TorrentEditRepository $repository): RedirectResponse
    {
        $torrent = $repository->update($request);

        $id = $torrent->id;
        $defaultUrl = "/web/details/$id?edited=1";
        $returl = $request->input('returnto', $defaultUrl);

        return redirect(SafeReturnUrl::filter(trim((string) $returl), $defaultUrl));
    }
}
