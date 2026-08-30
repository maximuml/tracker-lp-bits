<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\TorrentOperationAction;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Exceptions\NexusException;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\StaffMessage;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Support\Cache;
use App\Support\Config\SiteConfig;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\TorrentTags;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TorrentEditRepository extends BaseRepository
{
    public function __construct(
        private UploadRepository $uploadRepository,
    ) {}

    /**
     * @throws NexusException
     */
    public function update(Request $request): Torrent
    {
        $user = Auth::user();
        if (! $user) {
            throw new NexusException(Locale::trans('takeedit.missing_form_data', [], null));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            throw new NexusException(Locale::trans('takeedit.missing_form_data', [], null));
        }

        $torrentOld = Torrent::query()->find($id);
        if (! $torrentOld) {
            throw new NexusException(Locale::trans('takeedit.missing_form_data', [], null));
        }

        if ($user->id != $torrentOld->owner && ! Permission::canManageTorrent($user)) {
            throw new NexusException(Locale::trans('takeedit.not_owner', [], null));
        }

        $name = trim((string) $request->input('name', ''));
        $descr = (string) $request->input('descr', '');
        $categoryId = (int) $request->input('type', 0);

        if (empty($name) || empty($descr) || $categoryId <= 0) {
            throw new NexusException(Locale::trans('takeedit.missing_form_data', [], null));
        }

        $category = Category::query()->find($categoryId);
        if (! $category) {
            throw new NexusException(Locale::trans('upload.invalid_category', [], null));
        }

        $oldMode = (int) Category::query()->where('id', $torrentOld->category)->value('mode');
        $newMode = (int) $category->mode;
        if ($oldMode != $newMode && ! Permission::canMoveTorrent($user)) {
            throw new NexusException(Locale::trans('takeedit.cannot_move_torrent', [], null));
        }

        $siteConfig = SiteConfig::current();
        $maxPrice = $siteConfig->torrent->maxPrice();
        $paidTorrentEnabled = $siteConfig->torrent->paidTorrentEnabled();
        if ($maxPrice > 0 && $paidTorrentEnabled) {
            $price = (int) $request->input('price', 0);
            if ($price > $maxPrice) {
                throw new NexusException(Locale::trans('upload.price_too_much', [], null));
            }
        }

        /** @var array<string, mixed> $updateset */
        $updateset = [];
        /** @var array<string, mixed> $extraUpdate */
        $extraUpdate = [];

        $updateset['anonymous'] = $request->input('anonymous') ? 1 : 0;
        $updateset['name'] = $name;
        $extraUpdate['descr'] = $descr;
        $extraUpdate['media_info'] = (string) $request->input('technical_info', '');
        $updateset['url'] = null;
        $updateset['category'] = $categoryId;

        $subCategoriesAndTags = $this->uploadRepository->getSubCategoriesAndTags($request, $category, false);
        $subCategories = $subCategoriesAndTags['subCategories'];
        $tagIdArr = $subCategoriesAndTags['tags'];

        foreach (SearchBox::$taxonomies as $field => $info) {
            $updateset[$field] = $subCategories[$field] ?? 0;
        }

        if (Permission::canManageTorrent($user)) {
            $updateset['visible'] = $request->input('visible') ? 1 : 0;
        }

        if (Permission::canSetTorrentOnPromotion($user)) {
            $spState = TorrentPromotion::NORMAL->value;
            if ($request->has('sel_spstate')) {
                $selSpState = (int) $request->input('sel_spstate');
                if (in_array($selSpState, [2, 3, 4, 5, 6, 7])) {
                    $spState = $selSpState;
                }
            }
            $updateset['sp_state'] = $spState;

            $promotionTimeType = 0;
            $promotionUntil = null;
            if ($request->input('promotion_time_type') == 1) {
                $promotionTimeType = 1;
                $promotionUntil = null;
            } elseif ($request->input('promotion_time_type') == 2) {
                $promotionUntil = $request->input('promotionuntil');
                $addedTime = $torrentOld->added instanceof Carbon ? strtotime($torrentOld->added->toDateTimeString()) : 0;
                if (! empty($promotionUntil) && $addedTime !== false && $addedTime <= strtotime((string) $promotionUntil)) {
                    $promotionTimeType = 2;
                } else {
                    $promotionTimeType = 0;
                    $promotionUntil = null;
                }
            }
            $updateset['promotion_time_type'] = $promotionTimeType;
            $updateset['promotion_until'] = $promotionUntil;
        }

        if (Permission::canSetTorrentPosState($user) && $request->has('pos_state')) {
            $posState = $request->input('pos_state');
            if (isset(Torrent::$posStates[$posState])) {
                $posStateUntil = $request->input('pos_state_until') ?: null;
                if ($posState == TorrentPosState::NONE->value) {
                    $posStateUntil = null;
                }
                if ($posStateUntil && Carbon::parse($posStateUntil)->lte(now())) {
                    $posState = TorrentPosState::NONE->value;
                    $posStateUntil = null;
                }
                $updateset['pos_state'] = $posState;
                $updateset['pos_state_until'] = $posStateUntil;
            }
        }

        if (Permission::canSetTorrentHitAndRun($user) && ($request->has("hr.{$category->mode}") || $request->has('hr'))) {
            $updateset['hr'] = $this->uploadRepository->getHitAndRun($request, $category);
        }

        if (Permission::canSetTorrentPrice($user) && $paidTorrentEnabled) {
            $updateset['price'] = $this->uploadRepository->getPrice($request);
        }

        $updateset['cover'] = $this->uploadRepository->getCover($request);

        $torrentNew = DB::transaction(function () use ($id, $updateset, $extraUpdate, $request, $category, $tagIdArr) {
            /** @var array<string, mixed> $updateset */
            Torrent::query()->where('id', $id)->update($updateset);
            $torrentNew = Torrent::query()->findOrFail($id);
            $torrentNew->extra()->updateOrCreate(['torrent_id' => $id], $extraUpdate);

            $this->uploadRepository->saveCustomFields($request, $category, $id);
            TorrentTags::insert($id, $tagIdArr, (bool) true);

            return $torrentNew;
        });

        $this->writeEditLog($torrentOld, $torrentNew, $user);

        $torrentUrl = sprintf('details.php?id=%s', $torrentOld->id);
        if ($torrentOld->banned == 1 && $torrentOld->owner == $user->id) {
            StaffMessage::query()->insert([
                'sender' => $user->id,
                'subject' => Locale::trans('torrent.owner_update_torrent_subject', ['detail_url' => $torrentUrl, 'torrent_name' => $name], null),
                'msg' => Locale::trans('torrent.owner_update_torrent_msg', ['detail_url' => $torrentUrl, 'torrent_name' => $name], null),
                'added' => now(),
                'permission' => 'torrent-approval',
            ]);
            Cache::clearStaffMessage();
        }

        if ($torrentOld->owner != $user->id) {
            TorrentOperationLog::add([
                'torrent_id' => $torrentOld->id,
                'uid' => $user->id,
                'action_type' => TorrentOperationAction::EDIT->value,
                'comment' => '',
            ], true);
        }

        Events::fire(ModelEventEnum::TORRENT_UPDATED, $torrentNew, $torrentOld);

        try {
            $meiliSearch = app(MeiliSearchRepository::class);
            $meiliSearch->doImportFromDatabase($torrentOld->id);
        } catch (\Throwable $e) {
            Logger::writeWithContext((string) ('MeiliSearch update on edit failed: '.$e->getMessage()), (string) 'error', (bool) false);
        }

        return $torrentNew;
    }

    /**
     * @param  mixed  $user
     */
    private function writeEditLog(Torrent $torrentOld, Torrent $torrentNew, $user): void
    {
        $name = $torrentNew->name;
        $id = $torrentOld->id;

        if ($user->id == $torrentOld->owner) {
            if ($torrentOld->anonymous == 1) {
                Log::writeWithContext("Torrent $id ($name) was edited by Anonymous");
            } else {
                Log::writeWithContext("Torrent $id ($name) was edited by {$user->username}");
            }
        } else {
            Log::writeWithContext("Torrent $id ($name) was edited by {$user->username}, Mod Edit");
        }
    }
}
