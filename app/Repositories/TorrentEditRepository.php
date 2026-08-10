<?php

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Enums\Permission\PermissionEnum;
use App\Exceptions\NexusException;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\StaffMessage;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TorrentEditRepository extends BaseRepository
{
    public function __construct(
        private UploadRepository $uploadRepository,
    ) {
    }

    /**
     * @throws NexusException
     */
    public function update(Request $request): Torrent
    {
        $user = Auth::user();
        if (!$user) {
            throw new NexusException(nexus_trans('takeedit.missing_form_data'));
        }

        $id = (int) $request->input('id', 0);
        if ($id <= 0) {
            throw new NexusException(nexus_trans('takeedit.missing_form_data'));
        }

        $torrentOld = Torrent::query()->find($id);
        if (!$torrentOld) {
            throw new NexusException(nexus_trans('takeedit.missing_form_data'));
        }

        if ($user->id != $torrentOld->owner && !Permission::canManageTorrent($user)) {
            throw new NexusException(nexus_trans('takeedit.not_owner'));
        }

        $name = trim((string) $request->input('name', ''));
        $descr = (string) $request->input('descr', '');
        $categoryId = (int) $request->input('type', 0);

        if (empty($name) || empty($descr) || $categoryId <= 0) {
            throw new NexusException(nexus_trans('takeedit.missing_form_data'));
        }

        $category = Category::query()->find($categoryId);
        if (!$category) {
            throw new NexusException(nexus_trans('upload.invalid_category'));
        }

        $oldMode = (int) Category::query()->where('id', $torrentOld->category)->value('mode');
        $newMode = (int) $category->mode;
        if ($oldMode != $newMode && !Permission::canMoveTorrent($user)) {
            throw new NexusException(nexus_trans('takeedit.cannot_move_torrent'));
        }

        $siteConfig = \App\Support\Config\SiteConfig::current();
        $maxPrice = $siteConfig->torrent->maxPrice();
        $paidTorrentEnabled = $siteConfig->torrent->paidTorrentEnabled();
        if ($maxPrice > 0 && $paidTorrentEnabled) {
            $price = (int) $request->input('price', 0);
            if ($price > $maxPrice) {
                throw new NexusException(nexus_trans('upload.price_too_much'));
            }
        }

        $updateset = [];
        $extraUpdate = [];

        $updateset['anonymous'] = $request->input('anonymous') ? 'yes' : 'no';
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
            $updateset['visible'] = $request->input('visible') ? 'yes' : 'no';
        }

        if (Permission::canSetTorrentOnPromotion($user)) {
            $spState = Torrent::PROMOTION_NORMAL;
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
                if (!empty($promotionUntil) && strtotime($torrentOld->added) <= strtotime($promotionUntil)) {
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
                if ($posState == Torrent::POS_STATE_STICKY_NONE) {
                    $posStateUntil = null;
                }
                if ($posStateUntil && \Carbon\Carbon::parse($posStateUntil)->lte(now())) {
                    $posState = Torrent::POS_STATE_STICKY_NONE;
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
            Torrent::query()->where('id', $id)->update($updateset);
            $torrentNew = Torrent::query()->findOrFail($id);
            $torrentNew->extra()->updateOrCreate(['torrent_id' => $id], $extraUpdate);

            $this->uploadRepository->saveCustomFields($request, $category, $id);
            \insert_torrent_tags($id, $tagIdArr, true);

            return $torrentNew;
        });

        $this->writeEditLog($torrentOld, $torrentNew, $user);

        $torrentUrl = sprintf('details.php?id=%s', $torrentOld->id);
        if ($torrentOld->banned == 'yes' && $torrentOld->owner == $user->id) {
            StaffMessage::query()->insert([
                'sender' => $user->id,
                'subject' => nexus_trans('torrent.owner_update_torrent_subject', ['detail_url' => $torrentUrl, 'torrent_name' => $name]),
                'msg' => nexus_trans('torrent.owner_update_torrent_msg', ['detail_url' => $torrentUrl, 'torrent_name' => $name]),
                'added' => now(),
                'permission' => 'torrent-approval',
            ]);
            \clear_staff_message_cache();
        }

        if ($torrentOld->owner != $user->id) {
            TorrentOperationLog::add([
                'torrent_id' => $torrentOld->id,
                'uid' => $user->id,
                'action_type' => TorrentOperationLog::ACTION_TYPE_EDIT,
                'comment' => '',
            ], true);
        }

        fire_event(ModelEventEnum::TORRENT_UPDATED, $torrentNew, $torrentOld);

        try {
            $searchRep = new SearchRepository();
            $searchRep->updateTorrent($torrentOld->id);
        } catch (\Throwable $e) {
            \do_log('Search repository update on edit failed: ' . $e->getMessage(), 'error');
        }

        try {
            $meiliSearch = new MeiliSearchRepository();
            $meiliSearch->doImportFromDatabase($torrentOld->id);
        } catch (\Throwable $e) {
            \do_log('MeiliSearch update on edit failed: ' . $e->getMessage(), 'error');
        }

        return $torrentNew;
    }

    /**
     * @param  \App\Models\Torrent  $torrentOld
     * @param  \App\Models\Torrent  $torrentNew
     * @param  mixed  $user
     */
    private function writeEditLog(Torrent $torrentOld, Torrent $torrentNew, $user): void
    {
        $name = $torrentNew->name;
        $id = $torrentOld->id;

        if ($user->id == $torrentOld->owner) {
            if ($torrentOld->anonymous == 'yes') {
                \write_log("Torrent $id ($name) was edited by Anonymous");
            } else {
                \write_log("Torrent $id ($name) was edited by {$user->username}");
            }
        } else {
            \write_log("Torrent $id ($name) was edited by {$user->username}, Mod Edit");
        }
    }
}
