<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Permission;
use App\Enums\BusinessType;
use App\Enums\TorrentType;
use App\Events\TorrentCreated;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Exceptions\UploadValidationException;
use App\Models\Category;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Support\Config\SiteConfig;
use App\Support\CustomField;
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
        private UploadRepositories $repositories,
        private CustomField $customField,
        private MessageRepository $messageRepository,
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
        if (empty($request->descr)) {
            throw new UploadValidationException(Locale::trans('upload.blank_description', [], null), 'descr');
        }
        if (empty($request->type)) {
            throw new UploadValidationException(Locale::trans('upload.category_unselected', [], null), 'type');
        }
        $category = $this->repositories->category->findById((int) $request->type);
        if (! $category instanceof Category) {
            throw new UploadValidationException(Locale::trans('upload.invalid_category', [], null), 'type');
        }
        $torrentFile = $this->fileService->getTorrentFile($request);
        $filepath = $torrentFile->getRealPath();
        try {
            $dict = Bencode::load($filepath);
        } catch (ParseException $e) {
            Logger::writeWithContext((string) ('Bencode load error:'.$e->getMessage()), (string) 'error', (bool) false);
            throw new UploadValidationException('upload.not_bencoded_file', 'file');
        }
        $info = $this->fileService->checkTorrentDict($dict, 'info');
        if (isset($dict['piece layers']) || isset($info['files tree']) || (isset($info['meta version']) && $info['meta version'] == 2)) {
            throw new UploadValidationException('Torrent files created with Bittorrent Protocol v2, or hybrid torrents are not supported.', 'file');
        }
        $this->fileService->checkTorrentDict($info, 'piece length', 'integer');  // Only Check without use
        $dname = $this->fileService->checkTorrentDict($info, 'name', 'string');
        $pieces = $this->fileService->checkTorrentDict($info, 'pieces', 'string');
        if (strlen($pieces) % 20 != 0) {
            throw new UploadValidationException(Locale::trans('upload.invalid_pieces', [], null), 'file');
        }
        // The upload form documents the name as optional ("Taken from
        // filename if not specified") — fall back to the torrent's own
        // info.name so an empty field no longer fails the whole upload.
        $name = trim((string) ($request->name ?? ''));
        if ($name === '') {
            $name = trim((string) $dname);
        }
        if ($name === '') {
            throw new UploadValidationException(Locale::trans('upload.require_name', [], null), 'name');
        }
        $dict['info']['private'] = 1;
        $siteConfig = SiteConfig::current();
        $dict['info']['source'] = sprintf('[%s] %s', $siteConfig->basic->baseUrl(), $siteConfig->basic->siteName());
        unset($dict['announce-list']); // remove multi-tracker capability
        unset($dict['nodes']); // remove cached peers (Bitcomet & Azareus)

        $infoHash = pack('H*', sha1(Bencode::encode($dict['info'])));
        $existingId = $this->repositories->torrent->findIdByInfoHash($infoHash);
        if ($existingId !== null) {
            throw new TorrentAlreadyExistsException($existingId);
        }
        $subCategoriesAngTags = $this->metadataService->getSubCategoriesAndTags($request, $category);
        $fileListInfo = $this->fileService->getFileListInfo($info, $dname);
        $posStateInfo = $this->metadataService->getPosStateInfo($request);
        $anonymous = 0;
        $uploaderUsername = $user->username;
        if ($request->uplver == 'yes') {
            if (! Permission::canBeAnonymous()) {
                throw new UploadValidationException(Locale::trans('upload.no_permission_to_be_anonymous', [], null), 'uplver');
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
            'name' => $name,
            'size' => $fileListInfo['totalLength'],
            'numfiles' => count($fileListInfo['fileList']),
            'type' => TorrentType::fromStringSafe($fileListInfo['type'])->value,
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
            $newTorrent = $this->repositories->torrentUpload->createTorrent($torrentInsert);
            $id = $newTorrent->id;
            $torrentFilePath = "$torrentSavePath/$id.torrent";
            $saveResult = Bencode::dump($torrentFilePath, $dict);
            if ($saveResult === false) {
                Logger::writeWithContext((string) "save torrent failed: {$torrentFilePath}", (string) 'error', (bool) false);
                throw new UploadValidationException(Locale::trans('upload.save_torrent_file_failed', [], null), 'file');
            }
            $extraInsert['torrent_id'] = $id;
            $this->repositories->torrentUpload->insertExtra($extraInsert);
            $fileInsert = [];
            foreach ($fileListInfo['fileList'] as $fileItem) {
                $fileInsert[] = [
                    'torrent' => $id,
                    'filename' => $fileItem[0],
                    'size' => $fileItem[1],
                ];
            }
            $this->repositories->torrentUpload->insertFiles($fileInsert);
            if (! empty($subCategoriesAngTags['tags'])) {
                TorrentTags::insert($id, $subCategoriesAngTags['tags'], (bool) false);
            }
            $this->saveCustomFields($request, $category, $id);
            $this->sendReward($id);

            return $newTorrent;
        });
        $id = $newTorrent->id;
        $this->repositories->torrentDownload->addPiecesHashCache($id, $newTorrent->pieces_hash);
        $this->handleOffer($request, $newTorrent, $user);
        Log::writeWithContext("Torrent $id ($newTorrent->name) was uploaded by $uploaderUsername");
        event(new TorrentCreated($newTorrent));

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
            $this->repositories->bonus->add($user->id, $old, $delta, $new, "Upload torrent: $torrentId", BusinessType::UPLOAD_TORRENT->value);
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
        $this->customField->saveFieldValues($category->mode, $torrentId, $data);
    }

    private function handleOffer(Request $request, Torrent $torrent, User $user): void
    {
        $offerId = (int) $request->offer;
        if ($offerId <= 0) {
            return;
        }
        if (! $this->repositories->torrentUpload->isAllowedOffer($offerId, $user->id)) {
            return;
        }

        $voterIds = $this->repositories->torrentUpload->getOfferVoterIds($offerId, $user->id);
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
            $this->messageRepository->add([
                'sender' => null,
                'subject' => $subject,
                'receiver' => $voterId,
                'added' => now()->toDateTimeString(),
                'msg' => $msg,
            ]);
        }
        $this->repositories->torrentUpload->finalizeOffer($offerId, $user->id);
    }
}
