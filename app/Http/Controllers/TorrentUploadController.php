<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Requests\TorrentUploadRequest;
use App\Models\Offer;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\UploadRepository;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
use App\Support\Globals;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Locale;
use App\Support\Path;
use App\Support\Tracker;
use App\View\Components\BbcodeEditor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TorrentUploadController extends Controller
{
    public function __construct(
        private TorrentRepositoryInterface $torrentRepository,
        private SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        private TagRepositoryInterface $tagRepository,
        private HitAndRunRepository $hitAndRunRepository,
        private Globals $globals,
        private CurrentUser $currentUser,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login.php?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        // Views still read $lang_upload/$lang_edit from Globals (View composer
        // injects every global) — keep populating them until the per-key
        // __('legacy/x.k') conversion lands.
        $this->globals->set('lang_upload', (array) trans('legacy/upload'));
        $this->globals->set('lang_edit', (array) trans('legacy/edit'));

        /** @var array<string, string> $lang_upload */
        $lang_upload = (array) trans('legacy/upload');
        /** @var array<string, string> $lang_edit */
        $lang_edit = (array) trans('legacy/edit');

        if ($currentUser['parked']) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        if (! $currentUser['uploadpos']) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_unauthorized_to_upload'] ?? '', false);
        }

        $enableoffer = SiteConfig::current()->main->showOffer(false) ? 'yes' : 'no';
        $has_allowed_offer = 0;
        $offerRows = [];
        if ($enableoffer === 'yes') {
            $offerRows = Offer::query()
                ->where('allowed', OfferAllowed::ALLOWED->value)
                ->where('userid', $currentUser['id'])
                ->orderBy('name')
                ->get()
                ->toArray();
            $has_allowed_offer = count($offerRows);
        }

        $uploadFreely = LegacyResponse::canUpload('torrents');
        $allowtorrents = $has_allowed_offer || $uploadFreely;
        if (! $allowtorrents) {
            LegacyResponse::abort($lang_upload['std_sorry'] ?? '', $lang_upload['std_please_offer'] ?? '', false);
        }

        $browsecatmode = SiteConfig::current()->main->browseCat(1);
        $torrentConfig = SiteConfig::current()->torrent;

        $nameInputHtml = $this->torrentRepository->buildUploadFieldInput(
            'name', '', $lang_upload['text_torrent_name_note'] ?? '', $lang_upload['fill_setlist'] ?? '', 'setlistLookupBtn',
        );

        $priceCellHtml = '';
        if (Permission::can(PermissionEnum::TORRENT_SET_PRICE) && $torrentConfig->paidTorrentEnabled()) {
            $maxPrice = $torrentConfig->maxPrice();
            $pricePlaceholder = $maxPrice > 0
                ? Locale::trans('label.torrent.max_price_help', ['max_price' => $maxPrice], null)
                : '';
            $priceCellHtml = '<input type="number" min="0" name="price" placeholder="'.$pricePlaceholder.'" />&nbsp;&nbsp;'
                .Locale::trans('label.torrent.price_help', ['tax_factor' => $torrentConfig->taxFactor() * 100 .'%'], null);
        }

        $pickCellHtml = '';
        if (Permission::can(PermissionEnum::TORRENT_SET_STICKY)) {
            $options = '';
            foreach (Torrent::listPosStates() as $key => $value) {
                $options .= '<option value="'.$key.'">'.$value['text'].'</option>';
            }
            $pickCellHtml = '<b>'.$lang_edit['row_torrent_position'].':&nbsp;</b>'
                .'<select name="pos_state" style="width: 100px;">'.$options.'</select>&nbsp;&nbsp;&nbsp;'
                .view('components.datetime-input', ['label' => SafeHtml::fromTrustedHtml(Locale::trans('label.deadline', [], null).':&nbsp;'), 'name' => 'pos_state_until', 'value' => ''])->render();
        }

        $customField = new CustomField;

        return view('torrents.upload', [
            'uploadFreely' => $uploadFreely,
            'allowtorrents' => $allowtorrents,
            'offerRows' => $offerRows,
            'pageTitle' => $lang_upload['head_upload'] ?? '',
            'cats' => Category::listByModeWithContext($browsecatmode),
            'trackerUrl' => Tracker::schemaAndHost((int) ($currentUser['tracker_url_id'] ?? 0), true),
            'torrentDirWritable' => is_writable(Path::resolve((string) ($this->globals->get('torrent_dir') ?? ''), ROOT_PATH)),
            'nameInputHtml' => $nameInputHtml,
            'priceLabel' => Locale::trans('label.torrent.price', [], null),
            'priceCellHtml' => $priceCellHtml,
            'descrEditorHtml' => BbcodeEditor::html(['form' => 'upload', 'text' => 'descr', 'withPreview' => true]),
            'enableTechnicalInfo' => SiteConfig::current()->main->enableTechnicalInfo(),
            'taxonomySelectHtml' => $this->searchBoxSchemaBuilder->renderTaxonomySelect($browsecatmode),
            'customFieldsHtml' => $customField->renderOnUploadPage(0, $browsecatmode),
            'hitAndRunHtml' => $this->hitAndRunRepository->renderOnUploadPage('', $browsecatmode),
            'tagsHtml' => $this->tagRepository->renderCheckbox($browsecatmode),
            'pickCellHtml' => $pickCellHtml,
            'canBeAnonymous' => Permission::can(PermissionEnum::BE_ANONYMOUS),
        ]);
    }

    public function legacyStore(TorrentUploadRequest $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('details.php?id='.$e->getTorrentId().'&existed=1');
        }

        return redirect('details.php?id='.$torrent->id.'&uploaded=1');
    }
}
