<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentPosState;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Exceptions\UploadValidationException;
use App\Http\Requests\TorrentUploadRequest;
use App\Models\Offer;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\HitAndRunRepository;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\UploadRepository;
use App\Support\Category;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\CustomField;
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
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class TorrentUploadController extends Controller
{
    public function __construct(
        private TorrentRepositoryInterface $torrentRepository,
        private SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        private TagRepositoryInterface $tagRepository,
        private HitAndRunRepository $hitAndRunRepository,
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

        if ($currentUser['parked']) {
            LegacyResponse::abort(__('legacy/upload.std_sorry'), __('legacy/upload.std_unauthorized_to_upload'), false);
        }

        if (! $currentUser['uploadpos']) {
            LegacyResponse::abort(__('legacy/upload.std_sorry'), __('legacy/upload.std_unauthorized_to_upload'), false);
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
            LegacyResponse::abort(__('legacy/upload.std_sorry'), __('legacy/upload.std_please_offer'), false);
        }

        $browsecatmode = SiteConfig::current()->main->browseCat(1);
        $torrentConfig = SiteConfig::current()->torrent;

        $errorsBag = $request->hasSession() ? $request->session()->get('errors') : null;
        $fieldHasError = $errorsBag instanceof ViewErrorBag
            ? fn (string $field): bool => $errorsBag->has($field)
            : fn (string $field): bool => false;

        // Flatten the error bag into key+message+anchor rows so the template
        // only renders — ratchet tests forbid @php blocks in views.
        $errorAnchors = [
            'file' => '#torrent',
            'name' => '#name',
            'cnname' => '#cnname',
            'descr' => '#descr',
            'type' => '#browsecat',
            'technical_info' => '#technical_info',
            'price' => '#price',
            'offer' => '#offer',
            'uplver' => '#uplver',
            'pos_state' => '#pos_state',
            'pos_state_until' => '#datetime-picker-pos_state_until',
            'hr' => '#browsecat_section',
            'tags' => '#browsecat_section',
            'custom_fields' => '#browsecat_section',
        ];
        $uploadErrorList = [];
        if ($errorsBag instanceof ViewErrorBag) {
            $defaultBag = $errorsBag->getBag('default');
            foreach ($defaultBag->keys() as $errorKey) {
                $anchor = $errorAnchors[$errorKey] ?? (str_ends_with((string) $errorKey, '_sel') ? '#browsecat_section' : null);
                foreach ($defaultBag->get($errorKey) as $errorMessage) {
                    $uploadErrorList[] = ['message' => $errorMessage, 'anchor' => $anchor];
                }
            }
        }

        $nameInputHtml = $this->torrentRepository->buildUploadFieldInput(
            'name',
            $this->oldScalar($request, 'name'),
            SafeHtml::fromUntrustedHtml(__('legacy/upload.text_torrent_name_note')),
            __('legacy/upload.fill_setlist'),
            'setlistLookupBtn',
        );

        $priceCellHtml = '';
        if (Permission::can(PermissionEnum::TORRENT_SET_PRICE) && $torrentConfig->paidTorrentEnabled()) {
            $maxPrice = $torrentConfig->maxPrice();
            $pricePlaceholder = $maxPrice > 0
                ? Locale::trans('label.torrent.max_price_help', ['max_price' => $maxPrice], null)
                : '';
            $priceAria = $fieldHasError('price') ? ' aria-invalid="true" aria-describedby="price-error"' : '';
            $priceCellHtml = '<input type="number" min="0" id="price" name="price" value="'.e($this->oldScalar($request, 'price')).'" placeholder="'.$pricePlaceholder.'"'.$priceAria.' />&nbsp;&nbsp;'
                .Locale::trans('label.torrent.price_help', ['tax_factor' => $torrentConfig->taxFactor() * 100 .'%'], null);
        }

        $pickCellHtml = '';
        if (Permission::can(PermissionEnum::TORRENT_SET_STICKY)) {
            $posStateOld = $this->oldScalar($request, 'pos_state', (string) TorrentPosState::NONE->value);
            $options = '';
            foreach (Torrent::listPosStates() as $key => $value) {
                $options .= '<option value="'.$key.'"'.((string) $key === $posStateOld ? ' selected' : '').'>'.$value['text'].'</option>';
            }
            $posStateAria = $fieldHasError('pos_state') ? ' aria-invalid="true" aria-describedby="pos_state-error"' : '';
            $pickCellHtml = '<b>'.__('legacy/edit.row_torrent_position').':&nbsp;</b>'
                .'<select name="pos_state" id="pos_state" aria-label="'.e(__('legacy/edit.row_pick')).'"'.$posStateAria.'>'.$options.'</select>&nbsp;&nbsp;&nbsp;'
                .view('components.datetime-input', ['label' => SafeHtml::fromTrustedHtml(Locale::trans('label.deadline', [], null).':&nbsp;'), 'name' => 'pos_state_until', 'value' => $this->oldScalar($request, 'pos_state_until')])->render();
        }

        $taxonomyValues = [];
        foreach (SearchBox::$taxonomies as $field => $_taxonomy) {
            $oldValue = $request->old("{$field}_sel.{$browsecatmode}", $request->old($field));
            if (is_scalar($oldValue)) {
                $taxonomyValues[$field] = $oldValue;
            }
        }

        $oldTags = $request->old("tags.{$browsecatmode}", $request->old('tags', []));
        if (is_string($oldTags)) {
            $oldTags = explode(',', $oldTags);
        }
        $checkedTags = array_values(array_filter(array_map('intval', (array) $oldTags)));

        $oldHr = $request->old("hr.{$browsecatmode}", $request->old('hr', ''));

        $oldCustomFields = $request->old("custom_fields.{$browsecatmode}", []);
        $customField = new CustomField;

        return view('torrents.upload', [
            'uploadErrorList' => $uploadErrorList,
            'uploadFreely' => $uploadFreely,
            'allowtorrents' => $allowtorrents,
            'offerRows' => $offerRows,
            'pageTitle' => __('legacy/upload.head_upload'),
            'cats' => Category::listByModeWithContext($browsecatmode),
            'trackerUrl' => Tracker::schemaAndHost((int) ($currentUser['tracker_url_id'] ?? 0), true),
            'torrentDirWritable' => is_writable(Path::resolve(SiteConfig::current()->main->torrentDir(), ROOT_PATH)),
            'nameInputHtml' => SafeHtml::fromTrustedHtml($nameInputHtml),
            'priceLabel' => Locale::trans('label.torrent.price', [], null),
            'priceCellHtml' => SafeHtml::fromTrustedHtml($priceCellHtml),
            'descrEditorHtml' => SafeHtml::fromTrustedHtml(BbcodeEditor::html([
                'form' => 'upload',
                'text' => 'descr',
                'label' => __('legacy/upload.section_description'),
                'content' => $this->oldScalar($request, 'descr'),
                'withPreview' => true,
                'invalid' => $fieldHasError('descr'),
                'describedBy' => 'descr-error',
            ])),
            'enableTechnicalInfo' => SiteConfig::current()->main->enableTechnicalInfo(),
            'taxonomySelectHtml' => SafeHtml::fromTrustedHtml($this->searchBoxSchemaBuilder->renderTaxonomySelect($browsecatmode, $taxonomyValues)),
            'customFieldsHtml' => SafeHtml::fromTrustedHtml($customField->renderOnUploadPage(0, $browsecatmode, is_array($oldCustomFields) ? $oldCustomFields : [])),
            'hitAndRunHtml' => SafeHtml::fromTrustedHtml($this->hitAndRunRepository->renderOnUploadPage(is_scalar($oldHr) ? $oldHr : '', $browsecatmode)),
            'tagsHtml' => SafeHtml::fromTrustedHtml($this->tagRepository->renderCheckbox($browsecatmode, $checkedTags)),
            'pickCellHtml' => SafeHtml::fromTrustedHtml($pickCellHtml),
            'canBeAnonymous' => Permission::can(PermissionEnum::BE_ANONYMOUS),
        ]);
    }

    public function legacyStore(TorrentUploadRequest $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('details.php?id='.$e->getTorrentId().'&existed=1');
        } catch (UploadValidationException $e) {
            throw ValidationException::withMessages([
                $e->field() ?? 'upload' => $e->getMessage(),
            ])->redirectTo('/upload');
        } catch (NexusException $e) {
            throw ValidationException::withMessages([
                'upload' => $e->getMessage(),
            ])->redirectTo('/upload');
        }

        return redirect('details.php?id='.$torrent->id.'&uploaded=1');
    }

    /**
     * Flashed input can legitimately be an array (tags[4][], …); only scalar
     * values may be restored into single-value text/select controls.
     */
    private function oldScalar(Request $request, string $key, string $default = ''): string
    {
        $value = $request->old($key, $default);

        return is_scalar($value) ? (string) $value : $default;
    }
}
