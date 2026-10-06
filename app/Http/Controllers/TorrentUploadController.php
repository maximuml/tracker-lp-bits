<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\TagRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentPosState;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Exceptions\UploadValidationException;
use App\Http\Requests\TorrentUploadRequest;
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
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

class TorrentUploadController extends Controller
{
    public function __construct(private readonly OfferRepositoryInterface $offerRepository,
        private SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
        private TagRepositoryInterface $tagRepository,
        private HitAndRunRepository $hitAndRunRepository,
        private CurrentUser $currentUser,
        private CustomField $customField,
    ) {}

    public function create(Request $request): View|RedirectResponse
    {
        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return redirect('/login?returnto='.urlencode($request->fullUrl()));
        }

        $currentUser = $this->currentUser->get() ?? $user->toLegacyArray();
        $this->currentUser->set($currentUser);

        if ($currentUser['parked']) {
            LegacyResponse::abort(__('legacy/upload.std_sorry'), view('upload._unauthorized-upload')->render(), false);
        }

        if (! $currentUser['uploadpos']) {
            LegacyResponse::abort(__('legacy/upload.std_sorry'), view('upload._unauthorized-upload')->render(), false);
        }

        $enableoffer = SiteConfig::current()->main->showOffer(false) ? 'yes' : 'no';
        $has_allowed_offer = 0;
        $offerRows = [];
        if ($enableoffer === 'yes') {
            $offerRows = $this->offerRepository->listAllowedForUser((int) $currentUser['id'])
                ->toArray();
            $has_allowed_offer = count($offerRows);
        }

        $uploadFreely = LegacyResponse::canUpload('torrents');
        $allowtorrents = $has_allowed_offer || $uploadFreely;
        if (! $allowtorrents) {
            LegacyResponse::abort(__('legacy/upload.std_sorry'), view('upload._please-offer')->render(), false);
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

        $nameValue = $this->oldScalar($request, 'name');
        $nameInvalid = $fieldHasError('name');
        $descrContent = $this->oldScalar($request, 'descr');
        $descrInvalid = $fieldHasError('descr');

        $priceEnabled = Permission::can(PermissionEnum::TORRENT_SET_PRICE) && $torrentConfig->paidTorrentEnabled();
        $pricePlaceholder = $priceEnabled && $torrentConfig->maxPrice() > 0
            ? Locale::trans('label.torrent.max_price_help', ['max_price' => $torrentConfig->maxPrice()], null)
            : '';

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
        $customField = $this->customField;

        return view('torrents.upload', [
            'uploadErrorList' => $uploadErrorList,
            'uploadFreely' => $uploadFreely,
            'allowtorrents' => $allowtorrents,
            'offerRows' => $offerRows,
            'pageTitle' => __('legacy/upload.head_upload'),
            'cats' => Category::listByModeWithContext($browsecatmode),
            'trackerUrl' => Tracker::schemaAndHost((int) ($currentUser['tracker_url_id'] ?? 0), true),
            'torrentDirWritable' => is_writable(Path::resolve(SiteConfig::current()->main->torrentDir(), ROOT_PATH)),
            'nameValue' => $nameValue,
            'nameInvalid' => $nameInvalid,
            'priceLabel' => Locale::trans('label.torrent.price', [], null),
            'priceEnabled' => $priceEnabled,
            'priceValue' => $this->oldScalar($request, 'price'),
            'pricePlaceholder' => $pricePlaceholder,
            'priceInvalid' => $fieldHasError('price'),
            'priceHelp' => Locale::trans('label.torrent.price_help', ['tax_factor' => $torrentConfig->taxFactor() * 100 .'%'], null),
            'descrContent' => $descrContent,
            'descrInvalid' => $descrInvalid,
            'enableTechnicalInfo' => SiteConfig::current()->main->enableTechnicalInfo(),
            'taxonomySelectHtml' => SafeHtml::fromTrustedHtml($this->searchBoxSchemaBuilder->renderTaxonomySelect($browsecatmode, $taxonomyValues)),
            'customFieldsHtml' => SafeHtml::fromTrustedHtml($customField->renderOnUploadPage(0, $browsecatmode, is_array($oldCustomFields) ? $oldCustomFields : [])),
            'hitAndRunHtml' => SafeHtml::fromTrustedHtml($this->hitAndRunRepository->renderOnUploadPage(is_scalar($oldHr) ? $oldHr : '', $browsecatmode)),
            'tagsHtml' => SafeHtml::fromTrustedHtml($this->tagRepository->renderCheckbox($browsecatmode, $checkedTags)),
            'pickEnabled' => Permission::can(PermissionEnum::TORRENT_SET_STICKY),
            'posStates' => Torrent::listPosStates(),
            'posStateOld' => $this->oldScalar($request, 'pos_state', (string) TorrentPosState::NONE->value),
            'posStateInvalid' => $fieldHasError('pos_state'),
            'posStateUntil' => $this->oldScalar($request, 'pos_state_until'),
            'canBeAnonymous' => Permission::can(PermissionEnum::BE_ANONYMOUS),
        ]);
    }

    public function legacyStore(TorrentUploadRequest $request, UploadRepository $repository): RedirectResponse
    {
        try {
            $torrent = $repository->upload($request);
        } catch (TorrentAlreadyExistsException $e) {
            return redirect('/web/details/'.$e->getTorrentId().'?existed=1');
        } catch (UploadValidationException $e) {
            throw ValidationException::withMessages([
                $e->field() ?? 'upload' => $e->getMessage(),
            ])->redirectTo('/upload');
        } catch (NexusException $e) {
            throw ValidationException::withMessages([
                'upload' => $e->getMessage(),
            ])->redirectTo('/upload');
        }

        return redirect('/web/details/'.$torrent->id.'?uploaded=1');
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
