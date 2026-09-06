<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentPosState;
use App\Exceptions\NexusException;
use App\Http\Resources\SearchBoxResource;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\SearchBoxRepository;
use App\Repositories\TorrentUploadRepository;
use App\Support\Config\SiteConfig;
use App\Support\Description;
use App\Support\Locale;
use App\Support\Time;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UploadMetadataService
{
    /**
     * @return array<int|string, mixed>
     *
     * @throws NexusException
     */
    public function getSubCategoriesAndTags(Request $request, Category $category, bool $checkUploadPermission = true): array
    {
        $searchBoxRep = app(SearchBoxRepository::class);
        $sections = $searchBoxRep->listSections(SearchBox::listAllSectionId())->keyBy('id');
        if (! $sections->has($category->mode)) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        $section = $sections->get($category->mode);
        if (! $section instanceof SearchBox) {
            throw new NexusException(Locale::trans('upload.invalid_section', [], null));
        }
        if ($checkUploadPermission) {
            $this->canUploadToSection($request, $section);
        }

        $sectionResource = new SearchBoxResource($section);
        $sectionData = $sectionResource->response()->getData(true);
        $sectionInfo = $sectionData['data'];
        $categories = array_column($sectionInfo['categories'], 'id');
        if (! in_array($category->id, $categories)) {
            throw new NexusException(Locale::trans('upload.invalid_category', [], null));
        }
        $subCategoryInfo = array_column($sectionInfo['sub_categories'], null, 'field');
        $subCategories = [];
        foreach (SearchBox::$taxonomies as $name => $info) {
            $value = $this->getSubCategoryValue($request, (string) $name, $category->mode);
            if ($value > 0 && isset($subCategoryInfo[$name])) {
                $subCategoryValues = array_column($subCategoryInfo[$name]['data'], 'name', 'id');
                if (! isset($subCategoryValues[$value])) {
                    throw new NexusException(Locale::trans('upload.invalid_sub_category_value', ['field' => $name, 'label' => $subCategoryInfo[$name]['label'], 'value' => $value], null));
                }
            }
            $subCategories[$name] = $value > 0 && isset($subCategoryInfo[$name]) ? $value : 0;
        }

        $tags = $this->getTags($request, $category->mode);
        $allTags = array_column($sectionInfo['tags'], 'name', 'id');
        foreach ($tags as $tag) {
            if (! isset($allTags[$tag])) {
                throw new NexusException(Locale::trans('upload.invalid_tag', ['tag' => $tag], null));
            }
        }

        return compact('subCategories', 'tags');
    }

    public function getCover(Request $request): string
    {
        $descr = $request->descr ?? '';
        if (empty($descr)) {
            return '';
        }
        $descriptionArr = Description::parse($descr);

        return Description::firstImageUrl($descriptionArr, '');
    }

    public function getPrice(Request $request): int
    {
        $price = $request->price ?: 0;
        if (! is_numeric($price)) {
            throw new NexusException(Locale::trans('upload.invalid_price', ['price' => $price], null));
        }
        if ($price > 0) {
            if (! Permission::canSetTorrentPrice()) {
                throw new NexusException(Locale::trans('upload.no_permission_to_set_torrent_price', [], null));
            }
            $siteConfig = SiteConfig::current();
            if (! $siteConfig->torrent->paidTorrentEnabled()) {
                throw new NexusException(Locale::trans('upload.paid_torrent_not_enabled', [], null));
            }
            $maxPrice = $siteConfig->torrent->maxPrice();
            if ($maxPrice > 0 && $price > $maxPrice) {
                throw new NexusException(Locale::trans('upload.price_too_much', [], null));
            }
        }

        return intval($price);
    }

    public function getHitAndRun(Request $request, Category $category): int
    {
        $hr = $request->input("hr.{$category->mode}");
        if (! is_numeric($hr)) {
            $hr = $request->input('hr', 0);
        }
        $hr = (int) $hr;
        if ($hr > 0 && ! Permission::canSetTorrentHitAndRun()) {
            throw new NexusException(Locale::trans('upload.no_permission_to_set_torrent_hr', [], null));
        }
        if (! in_array($hr, [0, 1])) {
            throw new NexusException(Locale::trans('upload.invalid_hr', [], null));
        }

        return intval($hr);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getPosStateInfo(Request $request): array
    {
        $posState = $request->pos_state ?: TorrentPosState::NONE->value;
        $posStateUntil = $request->pos_state_until ?: null;
        if ($posState !== TorrentPosState::NONE->value) {
            if (! Permission::canSetTorrentPosState()) {
                throw new NexusException('upload.no_permission_to_set_torrent_pos_state');
            }
            if (! isset(Torrent::$posStates[$posState])) {
                throw new NexusException(Locale::trans('upload.invalid_pos_state', ['pos_state' => $posState], null));
            }
        }
        if ($posState == TorrentPosState::NONE->value) {
            $posStateUntil = null;
        }
        if ($posStateUntil && Carbon::parse($posStateUntil)->lt(Carbon::now())) {
            throw new NexusException(Locale::trans('upload.invalid_pos_state_until', [], null));
        }

        return compact('posState', 'posStateUntil');
    }

    public function getApprovalStatus(Request $request): int
    {
        if (Permission::canTorrentApprovalAllowAutomatic()) {
            return TorrentApprovalStatus::ALLOW->value;
        }

        return TorrentApprovalStatus::NONE->value;
    }

    private function canUploadToSection(Request $request, SearchBox $section): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new NexusException('Unauthenticated');
        }
        if (! $user->uploadpos) {
            throw new NexusException(Locale::trans('upload.unauthorized_to_upload', [], null));
        }

        $uploadDenyApprovalDenyCount = SiteConfig::current()->main->uploadDenyApprovalDenyCount();
        $approvalDenyCount = Torrent::query()->where('owner', $user->id)
            ->where('approval_status', TorrentApprovalStatus::DENY->value)
            ->count();
        if ($uploadDenyApprovalDenyCount > 0 && $approvalDenyCount >= $uploadDenyApprovalDenyCount) {
            throw new NexusException(Locale::trans('upload.approval_deny_reach_upper_limit', [], null));
        }

        if ($section->isSectionBrowse()) {
            $offerId = (int) $request->offer;
            if ($offerId > 0 && SiteConfig::current()->main->showOffer() && app(TorrentUploadRepository::class)->isAllowedOffer($offerId, $user->id)) {
                return true;
            }

            $offerSkipApprovedCount = SiteConfig::current()->main->offerSkipApprovedCount();
            if ($user->offer_allowed_count >= $offerSkipApprovedCount) {
                return true;
            }
            if (Time::isWeekendUploadOpen(Setting::getIsUploadOpenAtWeekend(), time())) {
                return true;
            }
            if (! Permission::canUploadToNormalSection()) {
                throw new NexusException(Locale::trans('upload.unauthorized_upload_freely', [], null));
            }

            return true;
        }
        throw new NexusException(Locale::trans('upload.invalid_section', [], null));
    }

    private function getSubCategoryValue(Request $request, string $name, int $mode): int
    {
        $legacyKey = "{$name}_sel.{$mode}";
        if ($request->has($legacyKey)) {
            $value = $request->input($legacyKey, 0);
        } else {
            $value = $request->get($name, 0);
        }

        return is_numeric($value) ? (int) $value : 0;
    }

    /**
     * @return array<int, mixed>
     */
    private function getTags(Request $request, int $mode): array
    {
        if ($request->has("tags.{$mode}")) {
            $tags = $request->input("tags.{$mode}", []);

            return is_array($tags) ? $tags : [];
        }

        $tags = $request->tags ?: [];
        if (! is_array($tags)) {
            $tags = explode(',', $tags);
        }

        return $tags;
    }
}
