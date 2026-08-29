<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\BusinessType;
use App\Enums\ModelEventEnum;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentPosState;
use App\Enums\TorrentPromotion;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Resources\SearchBoxResource;
use App\Models\BonusLogs;
use App\Models\Category;
use App\Models\File;
use App\Models\Message;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use App\Support\Config\SiteConfig;
use App\Support\CustomField;
use App\Support\Description;
use App\Support\Events;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Log;
use App\Support\Logger;
use App\Support\Path;
use App\Support\Time;
use App\Support\TorrentTags;
use App\Support\Url;
use App\Support\Validators;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Rhilip\Bencode\Bencode;
use Rhilip\Bencode\ParseException;

class UploadRepository extends BaseRepository
{
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
        $torrentFile = $this->getTorrentFile($request);
        $filepath = $torrentFile->getRealPath();
        try {
            $dict = Bencode::load($filepath);
        } catch (ParseException $e) {
            Logger::writeWithContext((string) ('Bencode load error:'.$e->getMessage()), (string) 'error', (bool) false);
            throw new NexusException('upload.not_bencoded_file');
        }
        $info = $this->checkTorrentDict($dict, 'info');
        if (isset($dict['piece layers']) || isset($info['files tree']) || (isset($info['meta version']) && $info['meta version'] == 2)) {
            throw new NexusException('Torrent files created with Bittorrent Protocol v2, or hybrid torrents are not supported.');
        }
        $this->checkTorrentDict($info, 'piece length', 'integer');  // Only Check without use
        $dname = $this->checkTorrentDict($info, 'name', 'string');
        $pieces = $this->checkTorrentDict($info, 'pieces', 'string');
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
        $subCategoriesAngTags = $this->getSubCategoriesAndTags($request, $category);
        $fileListInfo = $this->getFileListInfo($info, $dname);
        $posStateInfo = $this->getPosStateInfo($request);
        $anonymous = 'no';
        $uploaderUsername = $user->username;
        if ($request->uplver == 'yes') {
            if (! Permission::canBeAnonymous()) {
                throw new NexusException(Locale::trans('upload.no_permission_to_be_anonymous', [], null));
            }
            $anonymous = 'yes';
            $uploaderUsername = 'Anonymous';
        }
        $torrentSavePath = $this->getTorrentSavePath();
        $nowStr = Carbon::now()->toDateTimeString();
        $torrentInsert = [
            'filename' => $torrentFile->getClientOriginalName(),
            'owner' => $user->id,
            'visible' => 'yes',
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
            'sp_state' => $this->getSpState($fileListInfo['totalLength']),
            'added' => $nowStr,
            'last_action' => $nowStr,
            'info_hash' => $infoHash,
            'cover' => $this->getCover($request),
            'pieces_hash' => sha1($info['pieces']),
            'cache_stamp' => time(),
            'hr' => $this->getHitAndRun($request, $category),
            'pos_state' => $posStateInfo['posState'],
            'pos_state_until' => $posStateInfo['posStateUntil'],
            'approval_status' => $this->getApprovalStatus($request),
            'price' => $this->getPrice($request),
        ];
        $extraInsert = [
            'descr' => $request->descr ?? '',
            'media_info' => $request->technical_info ?? '',
            'nfo' => $this->getNfoContent($request),
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

    private function getTorrentFile(Request $request): UploadedFile
    {
        $file = $request->file('file');
        if (empty($file)) {
            throw new NexusException(Locale::trans('upload.missing_torrent_file', [], null));
        }
        if (! $file->isValid()) {
            Logger::writeWithContext((string) ('torrent file is invalid: '.$file->getClientOriginalName().' (error: '.$file->getError().')'), (string) 'error', (bool) false);
            throw new NexusException('upload torrent file error');
        }
        $size = $file->getSize();
        $maxAllowSize = SiteConfig::current()->main->maxTorrentSize();
        if ($size > $maxAllowSize) {
            $msg = sprintf('%s%s%s',
                Locale::trans('upload.torrent_file_too_big', [], null),
                number_format($maxAllowSize),
                Locale::trans('upload.remake_torrent_note', [], null)
            );
            throw new NexusException($msg);
        }
        if ($size == 0) {
            throw new NexusException('upload.empty_file');
        }
        $filename = $file->getClientOriginalName();
        if (! Validators::isUploadFilename($filename)) {
            throw new NexusException('upload.invalid_filename');
        }
        if (! preg_match('/^(.+)\.torrent$/si', $filename, $matches)) {
            throw new NexusException('upload.filename_not_torrent');
        }

        return $file;
    }

    private function getNfoContent(Request $request): string
    {
        $enableNfo = SiteConfig::current()->main->enableNfo();
        if (! $enableNfo) {
            return '';
        }
        $file = $request->file('nfo');
        if (empty($file)) {
            return '';
        }
        if (! $file->isValid()) {
            throw new NexusException(Locale::trans('upload.nfo_upload_failed', [], null));
        }
        $size = $file->getSize();
        if ($size == 0) {
            throw new NexusException(Locale::trans('upload.zero_byte_nfo', [], null));
        }
        if ($size > 65535) {
            throw new NexusException(Locale::trans('upload.nfo_too_big', [], null));
        }

        return str_replace("\x0d\x0d\x0a", "\x0d\x0a", $file->getContent());
    }

    private function getApprovalStatus(Request $request): int
    {
        if (Permission::canTorrentApprovalAllowAutomatic()) {
            return TorrentApprovalStatus::ALLOW->value;
        }

        return TorrentApprovalStatus::NONE->value;
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

    /**
     * @param  mixed  $dict
     * @param  mixed  $key
     * @param  mixed  $type
     * @return mixed
     */
    private function checkTorrentDict($dict, $key, $type = null)
    {
        if (! is_array($dict)) {
            throw new NexusException(Locale::trans('upload.not_a_dictionary', [], null));
        }
        if (! isset($dict[$key])) {
            throw new NexusException(Locale::trans('upload.dictionary_is_missing_key', [], null));
        }
        $value = $dict[$key];
        if ($type !== null) {
            $isFunction = 'is_'.$type;
            if (function_exists($isFunction) && ! $isFunction($value)) {
                throw new NexusException(Locale::trans('upload.invalid_entry_in_dictionary', [], null));
            }
        }

        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $info
     * @return array<int|string, mixed>
     *
     * @throws NexusException
     */
    private function getFileListInfo(array $info, string $dname): array
    {
        $filelist = [];
        $totallen = 0;
        if (isset($info['length'])) {
            $totallen = $info['length'];
            $filelist[] = [$dname, $totallen];
            $type = 'single';
        } else {
            $flist = $this->checkTorrentDict($info, 'files', 'array');

            if (! count($flist)) {
                throw new NexusException(Locale::trans('upload.empty_file', [], null));
            }
            foreach ($flist as $fn) {
                $ll = $this->checkTorrentDict($fn, 'length', 'integer');
                $path_key = isset($fn['path.utf-8']) ? 'path.utf-8' : 'path';
                $ff = $this->checkTorrentDict($fn, $path_key, 'list');

                $totallen += $ll;
                $ffa = [];
                foreach ($ff as $ffe) {
                    if (! is_string($ffe)) {
                        throw new NexusException(Locale::trans('upload.filename_errors', [], null));
                    }
                    $ffa[] = $ffe;
                }

                if (! count($ffa)) {
                    throw new NexusException(Locale::trans('upload.filename_errors', [], null));
                }
                $ffe = implode('/', $ffa);
                $filelist[] = [$ffe, $ll];
            }
            $type = 'multi';
        }

        return [
            'type' => $type,
            'totalLength' => $totallen,
            'fileList' => $filelist,
        ];
    }

    private function canUploadToSection(Request $request, SearchBox $section): bool
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new NexusException('Unauthenticated');
        }
        if ($user->uploadpos !== 'yes') {
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
            if ($offerId > 0 && SiteConfig::current()->main->showOffer() && TorrentUploadRepository::isAllowedOffer($offerId, $user->id)) {
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

    /** @param  mixed  $torrentSize */
    private function getSpState($torrentSize): int
    {
        $siteConfig = SiteConfig::current();
        $largeTorrentSize = $siteConfig->torrent->largeSize();
        if ($largeTorrentSize > 0 && $torrentSize > $largeTorrentSize * 1073741824) {
            $largeTorrentSpState = $siteConfig->torrent->largeSpState();
            if (TorrentPromotion::tryFrom((int) $largeTorrentSpState) !== null) {
                Logger::writeWithContext((string) "large torrent, sp state from config: {$largeTorrentSpState}", (string) 'info', (bool) false);

                return $largeTorrentSpState;
            }
            Logger::writeWithContext((string) "invalid large torrent sp state: {$largeTorrentSpState}", (string) 'error', (bool) false);

            return TorrentPromotion::NORMAL->value;
        } else {
            $torrentConfig = SiteConfig::current()->torrent;
            $probabilities = [
                TorrentPromotion::FREE->value => $torrentConfig->randomFreeProbability(),
                TorrentPromotion::TWO_TIMES_UP->value => $torrentConfig->randomTwoTimesUpProbability(),
                TorrentPromotion::FREE_TWO_TIMES_UP->value => $torrentConfig->randomFreeTwoTimesUpProbability(),
                TorrentPromotion::HALF_DOWN->value => $torrentConfig->randomHalfDownProbability(),
                TorrentPromotion::HALF_DOWN_TWO_TIMES_UP->value => $torrentConfig->randomHalfDownTwoTimesUpProbability(),
                TorrentPromotion::ONE_THIRD_DOWN->value => $torrentConfig->randomOneThirdDownProbability(),
            ];
            $sum = array_sum($probabilities);
            if ($sum == 0) {
                Logger::writeWithContext((string) 'no random sp state', (string) 'warning', (bool) false);

                return TorrentPromotion::NORMAL->value;
            }
            $random = mt_rand(1, $sum);
            $currentProbability = 0;
            foreach ($probabilities as $k => $v) {
                $currentProbability += $v;
                if ($random <= $currentProbability) {
                    Logger::writeWithContext((string) sprintf('random sp state, probabilities: %s, get result: %s by probability: %s', json_encode($probabilities), $k, $v), (string) 'info', (bool) false);

                    return $k;
                }
            }
            throw new \RuntimeException;
        }
    }

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

    private function getTorrentSavePath(): string
    {
        $torrentSavePath = Path::resolve(SiteConfig::current()->main->torrentDir(), \ROOT_PATH);
        if (! is_dir($torrentSavePath)) {
            Logger::writeWithContext((string) sprintf('torrentSavePath: %s not exists', $torrentSavePath), (string) 'error', (bool) false);
            throw new NexusException(Locale::trans('upload.torrent_save_dir_not_exists', [], null));
        }
        if (! is_writable($torrentSavePath)) {
            Logger::writeWithContext((string) sprintf('torrentSavePath: %s not writable', $torrentSavePath), (string) 'error', (bool) false);
            throw new NexusException(Locale::trans('upload.torrent_save_dir_not_writable', [], null));
        }

        return $torrentSavePath;
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

    /**
     * @param  mixed  $userId
     */
    public function sendEmailNotification(Torrent $torrent, $userId = 0): int
    {
        $logMsg = sprintf('torrent: %s, category: %s', $torrent->id, $torrent->category);
        $siteConfig = SiteConfig::current();
        if (! $siteConfig->smtp->emailNotify() || $siteConfig->smtp->type() == 'none') {
            Logger::writeWithContext((string) "{$logMsg}, not allow user receive email notification or smtp type is none", (string) 'info', (bool) false);

            return 0;
        }
        $page = 1;
        $size = 1000;
        $query = User::query()
            ->where('notifs', 'like', "%[cat$torrent->category]%")
            ->where('notifs', 'like', '%[email]%')
            ->normal();
        if ($userId > 0) {
            $query->where('id', $userId);
        }
        $total = (clone $query)->count();
        if ($total == 0) {
            Logger::writeWithContext((string) sprintf('%s, no user receive email notification', $logMsg), (string) 'info', (bool) false);

            return 0;
        }
        $toolRep = app(ToolRepository::class);
        $categoryName = $torrent->basic_category->name;
        $torrentUploader = $torrent->user;
        $successCount = 0;
        while (true) {
            $logPage = "$logMsg, page: $page";
            $users = (clone $query)->with(['language'])->forPage($page, $size)->get(['id', 'email', 'lang']);
            if ($users->isEmpty()) {
                Logger::writeWithContext((string) sprintf('%s, no more user', $logPage), (string) 'info', (bool) false);
                break;
            }
            foreach ($users as $user) {
                $locale = $user->locale;
                $logUser = "$logPage, user $user->id, locale: $locale";
                $subject = Locale::trans('upload.email_notification_subject', ['site_name' => SiteConfig::current()->basic->siteName()], $locale);
                $uploadByUsername = $torrentUploader instanceof User ? $torrentUploader->username : '';
                $description = $torrent->extra !== null ? ($torrent->extra->descr ?? '') : '';
                $body = Locale::trans('upload.email_notification_body', ['site_name' => SiteConfig::current()->basic->siteName(), 'name' => $torrent->name, 'size' => Format::size($torrent->size), 'category' => $categoryName, 'upload_by' => $this->handleAnonymous($uploadByUsername, $torrentUploader, $user, $torrent), 'description' => Str::limit(strip_tags(Format::formatComment($description)), 500), 'torrent_url' => sprintf('%s/details.php?id=%s&hit=1', Url::baseUrl(), $torrent->id)], $locale);
                $sendResult = $toolRep->sendMail($user->email, $subject, $body);
                Logger::writeWithContext((string) sprintf('%s, send result: %s', $logUser, $sendResult), (string) 'info', (bool) false);
                if ($sendResult) {
                    $successCount++;
                }
            }
            $page++;
        }
        Logger::writeWithContext((string) "{$logMsg}, receive email notification user total: {$total}, successCount: {$successCount}, done!", (string) 'info', (bool) false);

        return $successCount;
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
        if (! TorrentUploadRepository::isAllowedOffer($offerId, $user->id)) {
            return;
        }

        $voterIds = TorrentUploadRepository::getOfferVoterIds($offerId, $user->id);
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
        TorrentUploadRepository::finalizeOffer($offerId, $user->id);
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
