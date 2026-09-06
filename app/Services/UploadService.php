<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\BusinessType;
use App\Enums\ModelEventEnum;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Models\BonusLogs;
use App\Models\Category;
use App\Models\File;
use App\Models\Message;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use App\Repositories\TorrentRepository;
use App\Repositories\TorrentUploadRepository;
use App\Support\Config\SiteConfig;
use App\Support\CustomField;
use App\Support\Events;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\TorrentTags;
use App\Support\Url;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Rhilip\Bencode\Bencode;
use Rhilip\Bencode\ParseException;

class UploadService
{
    public function __construct(
        private UploadMetadataService $metadataService,
        private UploadFileService $fileService,
    ) {}

    /**
     * @return mixed
     *
     * @throws NexusException
     */
    public function upload(Request $request)
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new NexusException('Unauthenticated');
        }
        if (empty($request->name)) {
            throw new NexusException(Locale::trans('upload.require_name', [], null));
        }
        if (empty($request->descr)) {
            throw new NexusException(Locale::trans('upload.blank_description', [], null));
        }
        if (empty($request->type)) {
            throw new NexusException(Locale::trans('upload.category_unselected', [], null));
        }
        $category = Category::query()->find((int) $request->type);
        if (! $category instanceof Category) {
            throw new NexusException(Locale::trans('upload.invalid_category', [], null));
        }
        $torrentFile = $this->fileService->getTorrentFile($request);
        $filepath = $torrentFile->getRealPath();
        try {
            $dict = Bencode::load($filepath);
        } catch (ParseException $e) {
            Logger::writeWithContext((string) ('Bencode load error:'.$e->getMessage()), (string) 'error', (bool) false);
            throw new NexusException('upload.not_bencoded_file');
        }
        $info = $this->fileService->checkTorrentDict($dict, 'info');
        if (isset($dict['piece layers']) || isset($info['files tree']) || (isset($info['meta version']) && $info['meta version'] == 2)) {
            throw new NexusException('Torrent files created with Bittorrent Protocol v2, or hybrid torrents are not supported.');
        }
        $this->fileService->checkTorrentDict($info, 'piece length', 'integer');  // Only Check without use
        $dname = $this->fileService->checkTorrentDict($info, 'name', 'string');
        $pieces = $this->fileService->checkTorrentDict($info, 'pieces', 'string');
        if (strlen($pieces) % 20 != 0) {
            throw new NexusException(Locale::trans('upload.invalid_pieces', [], null));
        }
        $dict['info']['private'] = 1;
        $siteConfig = SiteConfig::current();
        $dict['info']['source'] = sprintf('[%s] %s', $siteConfig->basic->baseUrl(), $siteConfig->basic->siteName());
        unset($dict['announce-list']); // remove multi-tracker capability
        unset($dict['nodes']); // remove cached peers (Bitcomet & Azareus)

        $infoHash = pack('H*', sha1(Bencode::encode($dict['info'])));
        $exists = Torrent::query()->where('info_hash', $infoHash)->first(['id']);
        if ($exists) {
            throw new TorrentAlreadyExistsException($exists->id);
        }
        $subCategoriesAngTags = $this->metadataService->getSubCategoriesAndTags($request, $category);
        $fileListInfo = $this->fileService->getFileListInfo($info, $dname);
        $posStateInfo = $this->metadataService->getPosStateInfo($request);
        $anonymous = 0;
        $uploaderUsername = $user->username;
        if ($request->uplver == 'yes') {
            if (! Permission::canBeAnonymous()) {
                throw new NexusException(Locale::trans('upload.no_permission_to_be_anonymous', [], null));
            }
            $anonymous = 1;
            $uploaderUsername = 'Anonymous';
        }
        $torrentSavePath = $this->fileService->getTorrentSavePath();
        $nowStr = Carbon::now()->toDateTimeString();
        $torrentInsert = [
            'filename' => $torrentFile->getClientOriginalName(),
            'owner' => $user->id,
            'visible' => 1,
            'anonymous' => $anonymous,
            'name' => $request->name,
            'size' => $fileListInfo['totalLength'],
            'numfiles' => count($fileListInfo['fileList']),
            'type' => $fileListInfo['type'],
            'url' => null,
            'category' => $category->id,
            'source' => $subCategoriesAngTags['subCategories']['source'],
            'medium' => $subCategoriesAngTags['subCategories']['medium'],
            'codec' => $subCategoriesAngTags['subCategories']['codec'],
            'audiocodec' => $subCategoriesAngTags['subCategories']['audiocodec'],
            'standard' => $subCategoriesAngTags['subCategories']['standard'],
            'processing' => $subCategoriesAngTags['subCategories']['processing'],
            'save_as' => $dname,
            'sp_state' => $this->fileService->getSpState($fileListInfo['totalLength']),
            'added' => $nowStr,
            'last_action' => $nowStr,
            'info_hash' => $infoHash,
            'cover' => $this->metadataService->getCover($request),
            'pieces_hash' => sha1($info['pieces']),
            'cache_stamp' => time(),
            'hr' => $this->metadataService->getHitAndRun($request, $category),
            'pos_state' => $posStateInfo['posState'],
            'pos_state_until' => $posStateInfo['posStateUntil'],
            'approval_status' => $this->metadataService->getApprovalStatus($request),
            'price' => $this->metadataService->getPrice($request),
        ];
        $extraInsert = [
            'descr' => $request->descr ?? '',
            'media_info' => $request->technical_info ?? '',
            'nfo' => $this->fileService->getNfoContent($request),
            'created_at' => $nowStr,
        ];
        $newTorrent = DB::transaction(function () use ($request, $category, $torrentInsert, $extraInsert, $fileListInfo, $subCategoriesAngTags, $dict, $torrentSavePath) {
            $newTorrent = Torrent::query()->create($torrentInsert);
            $id = $newTorrent->id;
            $torrentFilePath = "$torrentSavePath/$id.torrent";
            $saveResult = Bencode::dump($torrentFilePath, $dict);
            if ($saveResult === false) {
                Logger::writeWithContext((string) "save torrent failed: {$torrentFilePath}", (string) 'error', (bool) false);
                throw new NexusException(Locale::trans('upload.save_torrent_file_failed', [], null));
            }
            $extraInsert['torrent_id'] = $id;
            TorrentExtra::query()->insert($extraInsert);
            $fileInsert = [];
            foreach ($fileListInfo['fileList'] as $fileItem) {
                $fileInsert[] = [
                    'torrent' => $id,
                    'filename' => $fileItem[0],
                    'size' => $fileItem[1],
                ];
            }
            File::query()->insert($fileInsert);
            if (! empty($subCategoriesAngTags['tags'])) {
                TorrentTags::insert($id, $subCategoriesAngTags['tags'], (bool) false);
            }
            $this->saveCustomFields($request, $category, $id);
            $this->sendReward($id);

            return $newTorrent;
        });
        $id = $newTorrent->id;
        $torrentRep = app(TorrentRepository::class);
        $torrentRep->addPiecesHashCache($id, $newTorrent->pieces_hash);
        $this->handleOffer($request, $newTorrent, $user);
        Log::writeWithContext("Torrent $id ($newTorrent->name) was uploaded by $uploaderUsername");
        Events::fire(ModelEventEnum::TORRENT_CREATED, $newTorrent, null);

        return $newTorrent;
    }

    /** @param  mixed  $torrentId */
    private function sendReward($torrentId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new NexusException('Unauthenticated');
        }
        $seedbonus = $user->seedbonus;
        $old = is_numeric($seedbonus) ? (float) $seedbonus : 0.0;
        $delta = SiteConfig::current()->bonus->uploadTorrent();
        if ($delta > 0) {
            $new = $old + $delta;
            $user->increment('seedbonus', $delta);
            BonusLogs::add($user->id, $old, $delta, $new, "Upload torrent: $torrentId", BusinessType::UPLOAD_TORRENT->value);
            Logger::writeWithContext((string) "upload torrent: {$torrentId}, success send reward: {$delta}", (string) 'info', (bool) false);
        } else {
            Logger::writeWithContext((string) "upload torrent: {$torrentId}, no reward", (string) 'info', (bool) false);
        }
    }

    public function saveCustomFields(Request $request, Category $category, int $torrentId): void
    {
        if (! $request->has('custom_fields')) {
            return;
        }
        $data = $request->input("custom_fields.{$category->mode}", []);
        if (empty($data)) {
            return;
        }
        $field = new CustomField;
        $field->saveFieldValues($category->mode, $torrentId, $data);
    }

    private function handleOffer(Request $request, Torrent $torrent, User $user): void
    {
        $offerId = (int) $request->offer;
        if ($offerId <= 0) {
            return;
        }
        if (! app(TorrentUploadRepository::class)->isAllowedOffer($offerId, $user->id)) {
            return;
        }

        $voterIds = app(TorrentUploadRepository::class)->getOfferVoterIds($offerId, $user->id);
        foreach ($voterIds as $voterId) {
            $locale = Locale::userLocale($voterId);
            $msg = Locale::trans('torrent.msg_offer_you_voted', [], $locale)
                .$torrent->name
                .Locale::trans('torrent.msg_was_uploaded_by', [], $locale)
                .$user->username
                .Locale::trans('torrent.msg_you_can_download', [], $locale)
                .'[url='.Url::schemeAndHost().'/details.php?id='.$torrent->id.'&hit=1]'
                .Locale::trans('torrent.msg_here', [], $locale)
                .'[/url]';
            $subject = Locale::trans('torrent.msg_offer', [], $locale)
                .$torrent->name
                .Locale::trans('torrent.msg_was_just_uploaded', [], $locale);
            Message::add([
                'sender' => null,
                'subject' => $subject,
                'receiver' => $voterId,
                'added' => now()->toDateTimeString(),
                'msg' => $msg,
            ]);
        }
        app(TorrentUploadRepository::class)->finalizeOffer($offerId, $user->id);
    }
}
