<?php
namespace App\Repositories;

use App\Auth\Permission;
use App\Enums\ModelEventEnum;
use App\Exceptions\NexusException;
use App\Exceptions\TorrentAlreadyExistsException;
use App\Http\Resources\SearchBoxResource;
use App\Models\BonusLogs;
use App\Models\Category;
use App\Models\File;
use App\Models\Message;
use App\Models\SearchBox;
use App\Models\Torrent;
use App\Models\TorrentExtra;
use App\Models\User;
use App\Repositories\TorrentUploadRepository;
use App\Support\Locale;
use App\Support\Url;
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
     * @param  \Illuminate\Http\Request  $request
     * @return  mixed
     * @throws NexusException
     */
    public function upload(Request $request)
    {
        $user = $request->user();
        if (empty($request->name)) {
            throw new NexusException(nexus_trans("upload.require_name"));
        }
        if (empty($request->descr)) {
            throw new NexusException(nexus_trans("upload.blank_description"));
        }
        if (empty($request->type)) {
            throw new NexusException(nexus_trans("upload.category_unselected"));
        }
        $category = Category::query()->find($request->type);
        if (!$category) {
            throw new NexusException(nexus_trans("upload.invalid_category"));
        }
        $torrentFile = $this->getTorrentFile($request);
        $filepath = $torrentFile->getRealPath();
        try {
            $dict = Bencode::load($filepath);
        } catch (ParseException $e) {
            do_log("Bencode load error:" . $e->getMessage(), 'error');
            throw new NexusException("upload.not_bencoded_file");
        }
        $info = $this->checkTorrentDict($dict, 'info');
        if (isset($dict['piece layers']) || isset($info['files tree']) || (isset($info['meta version']) && $info['meta version'] == 2)) {
            throw new NexusException("Torrent files created with Bittorrent Protocol v2, or hybrid torrents are not supported.");
        }
        $this->checkTorrentDict($info, 'piece length', 'integer');  // Only Check without use
        $dname = $this->checkTorrentDict($info, 'name', 'string');
        $pieces = $this->checkTorrentDict($info, 'pieces', 'string');
        if (strlen($pieces) % 20 != 0) {
            throw new NexusException(nexus_trans("upload.invalid_pieces"));
        }
        $dict['info']['private'] = 1;
        $siteConfig = \App\Support\Config\SiteConfig::current();
        $dict['info']['source'] = sprintf("[%s] %s", $siteConfig->basic->baseUrl(), $siteConfig->basic->siteName());
        unset ($dict['announce-list']); // remove multi-tracker capability
        unset ($dict['nodes']); // remove cached peers (Bitcomet & Azareus)

        $infoHash = pack("H*", sha1(Bencode::encode($dict['info'])));
        $exists = Torrent::query()->where('info_hash', $infoHash)->first(['id']);
        if ($exists) {
            throw new TorrentAlreadyExistsException($exists->id);
        }
        $subCategoriesAngTags = $this->getSubCategoriesAndTags($request, $category);
        $fileListInfo = $this->getFileListInfo($info, $dname);
        $posStateInfo = $this->getPosStateInfo($request);
        $anonymous = "no";
        $uploaderUsername = $user->username;
        if ($request->uplver == 'yes') {
            if (!Permission::canBeAnonymous()) {
                throw new NexusException(nexus_trans('upload.no_permission_to_be_anonymous'));
            }
            $anonymous = "yes";
            $uploaderUsername = "Anonymous";
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
                do_log("save torrent failed: $torrentFilePath", 'error');
                throw new NexusException(nexus_trans('upload.save_torrent_file_failed'));
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
            if (!empty($subCategoriesAngTags['tags'])) {
                insert_torrent_tags($id, $subCategoriesAngTags['tags']);
            }
            $this->saveCustomFields($request, $category, $id);
            $this->sendReward($id);
            return $newTorrent;
        });
        $id = $newTorrent->id;
        $torrentRep = new TorrentRepository();
        $torrentRep->addPiecesHashCache($id, $newTorrent->pieces_hash);
        $this->handleOffer($request, $newTorrent, $user);
        write_log("Torrent $id ($newTorrent->name) was uploaded by $uploaderUsername");
        fire_event(ModelEventEnum::TORRENT_CREATED, $newTorrent);
        return $newTorrent;
    }

    /** @param  \Illuminate\Http\Request  $request */
    private function getTorrentFile(Request $request): UploadedFile
    {
        $file = $request->file('file');
        if (empty($file)) {
            throw new NexusException(nexus_trans('upload.missing_torrent_file'));
        }
        if (!$file->isValid()) {
            do_log("torrent file is invalid: " . $file->getClientOriginalName() . ' (error: ' . $file->getError() . ')', 'error');
            throw new NexusException("upload torrent file error");
        }
        $size = $file->getSize();
        $maxAllowSize = \App\Support\Config\SiteConfig::current()->main->maxTorrentSize();
        if ($size > $maxAllowSize) {
            $msg = sprintf("%s%s%s",
                nexus_trans("upload.torrent_file_too_big"),
                number_format($maxAllowSize),
                nexus_trans("upload.remake_torrent_note")
            );
            throw new NexusException($msg);
        }
        if ($size == 0) {
            throw new NexusException("upload.empty_file");
        }
        $filename = $file->getClientOriginalName();
        if (!validfilename($filename)) {
            throw new NexusException("upload.invalid_filename");
        }
        if (!preg_match('/^(.+)\.torrent$/si', $filename, $matches)) {
            throw new NexusException("upload.filename_not_torrent");
        }
        return $file;
    }

    /** @param  \Illuminate\Http\Request  $request */
    private function getNfoContent(Request $request): string
    {
        $enableNfo = \App\Support\Config\SiteConfig::current()->main->enableNfo();
        if (!$enableNfo) {
            return '';
        }
        $file = $request->file('nfo');
        if (empty($file)) {
            return '';
        }
        if (!$file->isValid()) {
            throw new NexusException(nexus_trans("upload.nfo_upload_failed"));
        }
        $size = $file->getSize();
        if ($size == 0) {
            throw new NexusException(nexus_trans("upload.zero_byte_nfo"));
        }
        if ($size > 65535) {
            throw new NexusException(nexus_trans("upload.nfo_too_big"));
        }
        return str_replace("\x0d\x0d\x0a", "\x0d\x0a", $file->getContent());
    }

    /** @param  \Illuminate\Http\Request  $request */
    private function getApprovalStatus(Request $request): int
    {
        if (Permission::canTorrentApprovalAllowAutomatic()) {
            return Torrent::APPROVAL_STATUS_ALLOW;
        }
        return Torrent::APPROVAL_STATUS_NONE;
    }

    /** @param  \Illuminate\Http\Request  $request */
    public function getPrice(Request $request): int
    {
        $price =  $request->price ?: 0;
        if (!is_numeric($price)) {
            throw new NexusException(nexus_trans('upload.invalid_price', ['price' => $price]));
        }
        if ($price > 0) {
            if (!Permission::canSetTorrentPrice()) {
                throw new NexusException(nexus_trans("upload.no_permission_to_set_torrent_price"));
            }
            $siteConfig = \App\Support\Config\SiteConfig::current();
            if (!$siteConfig->torrent->paidTorrentEnabled()) {
                throw new NexusException(nexus_trans("upload.paid_torrent_not_enabled"));
            }
            $maxPrice = $siteConfig->torrent->maxPrice();
            if ($maxPrice > 0 && $price > $maxPrice) {
                throw new NexusException(nexus_trans('upload.price_too_much'));
            }
        }
        return intval($price);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     */
    public function getHitAndRun(Request $request, Category $category): int
    {
        $hr = $request->input("hr.{$category->mode}");
        if (!is_numeric($hr)) {
            $hr = $request->input('hr', 0);
        }
        $hr = (int) $hr;
        if ($hr > 0 && !Permission::canSetTorrentHitAndRun()) {
            throw new NexusException(nexus_trans("upload.no_permission_to_set_torrent_hr"));
        }
        if (!in_array($hr, [0, 1])) {
            throw new NexusException(nexus_trans('upload.invalid_hr'));
        }
        return intval($hr);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @return  array<int|string, mixed>
     */
    public function getPosStateInfo(Request $request): array
    {
        $posState = $request->pos_state ?: Torrent::POS_STATE_STICKY_NONE;
        $posStateUntil = $request->pos_state_until ?: null;
        if ($posState !== Torrent::POS_STATE_STICKY_NONE) {
            if (!Permission::canSetTorrentPosState()) {
                throw new NexusException("upload.no_permission_to_set_torrent_pos_state");
            }
            if (!isset(Torrent::$posStates[$posState])) {
                throw new NexusException(nexus_trans('upload.invalid_pos_state', ['pos_state' => $posState]));
            }
        }
        if ($posState == Torrent::POS_STATE_STICKY_NONE) {
            $posStateUntil = null;
        }
        if ($posStateUntil && Carbon::parse($posStateUntil)->lt(Carbon::now())) {
            throw new NexusException(nexus_trans('upload.invalid_pos_state_until'));
        }
        return compact('posState', 'posStateUntil');
    }

    /**
     * @param  mixed  $dict
     * @param  mixed  $key
     * @param  mixed  $type
     * @return  mixed
     */
    private function checkTorrentDict($dict, $key, $type = null)
    {
        if (!is_array($dict)) {
            throw new NexusException(nexus_trans("upload.not_a_dictionary"));
        }
        if (!isset($dict[$key])) {
            throw new NexusException(nexus_trans("upload.dictionary_is_missing_key"));
        }
        $value = $dict[$key];
        if (!is_null($type)) {
            $isFunction = 'is_' . $type;
            if (function_exists($isFunction) && !$isFunction($value)) {
                throw new NexusException(nexus_trans("upload.invalid_entry_in_dictionary"));
            }
        }
        return $value;
    }

    /**
     * @param  array<int|string, mixed>  $info
     * @param  string  $dname
     * @return  array<int|string, mixed>
     * @throws NexusException
     */
    private function getFileListInfo(array $info, string $dname): array
    {
        $filelist = array();
        $totallen = 0;
        if (isset($info['length'])) {
            $totallen = $info['length'];
            $filelist[] = array($dname, $totallen);
            $type = "single";
        } else {
            $flist = $this->checkTorrentDict($info, 'files', 'array');

            if (!count($flist)) {
                throw new NexusException(nexus_trans("upload.empty_file"));
            }
            foreach ($flist as $fn) {
                $ll = $this->checkTorrentDict($fn, 'length', 'integer');
                $path_key = isset($fn['path.utf-8']) ? 'path.utf-8' : 'path';
                $ff = $this->checkTorrentDict($fn, $path_key, 'list');

                $totallen += $ll;
                $ffa = array();
                foreach ($ff as $ffe) {
                    if (!is_string($ffe)) {
                        throw new NexusException(nexus_trans("upload.filename_errors"));
                    }
                    $ffa[] = $ffe;
                }

                if (!count($ffa)) {
                    throw new NexusException(nexus_trans("upload.filename_errors"));
                }
                $ffe = implode("/", $ffa);
                $filelist[] = array($ffe, $ll);
            }
            $type = "multi";
        }
        return [
            'type' => $type,
            'totalLength' => $totallen,
            'fileList' => $filelist,
        ];
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\SearchBox  $section
     */
    private function canUploadToSection(Request $request, SearchBox $section): bool
    {
        $user = Auth::user();
        if ($user->uploadpos !== 'yes') {
            throw new NexusException(nexus_trans('upload.unauthorized_to_upload'));
        }

        $uploadDenyApprovalDenyCount = \App\Support\Config\SiteConfig::current()->main->uploadDenyApprovalDenyCount();
        $approvalDenyCount = Torrent::query()->where('owner', $user->id)
            ->where('approval_status', Torrent::APPROVAL_STATUS_DENY)
            ->count()
        ;
        if ($uploadDenyApprovalDenyCount > 0 && $approvalDenyCount >= $uploadDenyApprovalDenyCount) {
            throw new NexusException(nexus_trans("upload.approval_deny_reach_upper_limit"));
        }

        if ($section->isSectionBrowse()) {
            $offerId = (int) $request->offer;
            if ($offerId > 0 && \App\Support\Config\SiteConfig::current()->main->showOffer() && TorrentUploadRepository::isAllowedOffer($offerId, $user->id)) {
                return true;
            }

            $offerSkipApprovedCount = \App\Support\Config\SiteConfig::current()->main->offerSkipApprovedCount();
            if ($user->offer_allowed_count >= $offerSkipApprovedCount) {
                return true;
            }
            if (get_if_restricted_is_open()) {
                return true;
            }
            if (!Permission::canUploadToNormalSection()) {
                throw new NexusException(nexus_trans('upload.unauthorized_upload_freely'));
            }
            return true;
        }
        throw new NexusException(nexus_trans('upload.invalid_section'));
    }

    /** @param  mixed  $torrentSize */
    private function getSpState($torrentSize): int
    {
        $siteConfig = \App\Support\Config\SiteConfig::current();
        $largeTorrentSize = $siteConfig->torrent->largeSize();
        if ($largeTorrentSize > 0 && $torrentSize > $largeTorrentSize * 1073741824) {
            $largeTorrentSpState = $siteConfig->torrent->largeSpState();
            if (isset(Torrent::$promotionTypes[$largeTorrentSpState])) {
                do_log("large torrent, sp state from config: $largeTorrentSpState");
                return $largeTorrentSpState;
            }
            do_log("invalid large torrent sp state: $largeTorrentSpState", 'error');
            return Torrent::PROMOTION_NORMAL;
        } else {
            $torrentConfig = \App\Support\Config\SiteConfig::current()->torrent;
        $probabilities = [
                Torrent::PROMOTION_FREE => $torrentConfig->randomFreeProbability(),
                Torrent::PROMOTION_TWO_TIMES_UP => $torrentConfig->randomTwoTimesUpProbability(),
                Torrent::PROMOTION_FREE_TWO_TIMES_UP => $torrentConfig->randomFreeTwoTimesUpProbability(),
                Torrent::PROMOTION_HALF_DOWN => $torrentConfig->randomHalfDownProbability(),
                Torrent::PROMOTION_HALF_DOWN_TWO_TIMES_UP => $torrentConfig->randomHalfDownTwoTimesUpProbability(),
                Torrent::PROMOTION_ONE_THIRD_DOWN => $torrentConfig->randomOneThirdDownProbability(),
            ];
            $sum = array_sum($probabilities);
            if ($sum == 0) {
                do_log("no random sp state", 'warning');
                return Torrent::PROMOTION_NORMAL;
            }
            $random = mt_rand(1, $sum);
            $currentProbability = 0;
            foreach ($probabilities as $k => $v) {
                $currentProbability += $v;
                if ($random <= $currentProbability) {
                    do_log(sprintf("random sp state, probabilities: %s, get result: %s by probability: %s", json_encode($probabilities), $k, $v));
                    return $k;
                }
            }
            throw new \RuntimeException();
        }
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     * @param  bool  $checkUploadPermission
     * @return  array<int|string, mixed>
     * @throws NexusException
     */
    public function getSubCategoriesAndTags(Request $request, Category $category, bool $checkUploadPermission = true): array
    {
        $searchBoxRep = new SearchBoxRepository();
        $sections = $searchBoxRep->listSections(SearchBox::listAllSectionId())->keyBy('id');
        if (!$sections->has($category->mode)) {
            throw new NexusException(nexus_trans('upload.invalid_section'));
        }
        $section = $sections->get($category->mode);
        if (!$section instanceof SearchBox) {
            throw new NexusException(nexus_trans('upload.invalid_section'));
        }
        if ($checkUploadPermission) {
            $this->canUploadToSection($request, $section);
        }

        $sectionResource = new SearchBoxResource($section);
        $sectionData = $sectionResource->response()->getData(true);
        $sectionInfo = $sectionData['data'];
        $categories = array_column($sectionInfo['categories'], 'id');
        if (!in_array($category->id, $categories)) {
            throw new NexusException(nexus_trans('upload.invalid_category'));
        }
        $subCategoryInfo = array_column($sectionInfo['sub_categories'], null, 'field');
        $subCategories = [];
        foreach (SearchBox::$taxonomies as $name => $info) {
            $value = $this->getSubCategoryValue($request, $name, $category->mode);
            if ($value > 0 && isset($subCategoryInfo[$name])) {
                $subCategoryValues = array_column($subCategoryInfo[$name]['data'], 'name', 'id');
                if (!isset($subCategoryValues[$value])) {
                    throw new NexusException(nexus_trans(
                        'upload.invalid_sub_category_value',
                        ['field' => $name, 'label' => $subCategoryInfo[$name]['label'], 'value' => $value]
                    ));
                }
            }
            $subCategories[$name] = $value > 0 && isset($subCategoryInfo[$name]) ? $value : 0;
        }

        $tags = $this->getTags($request, $category->mode);
        $allTags = array_column($sectionInfo['tags'], 'name', 'id');
        foreach ($tags as $tag) {
            if (!isset($allTags[$tag])) {
                throw new NexusException(nexus_trans('upload.invalid_tag', ['tag' => $tag]));
            }
        }
        return compact('subCategories', 'tags');
    }

    /** @param  \Illuminate\Http\Request  $request */
    public function getCover(Request $request):string
    {
        $descr = $request->descr ?? '';
        if (empty($descr)) {
            return '';
        }
        $descriptionArr = format_description($descr);
        return get_image_from_description($descriptionArr, true, false);
    }

    private function getTorrentSavePath(): string
    {
        $torrentSavePath = getFullDirectory(\App\Support\Config\SiteConfig::current()->main->torrentDir());
        if (!is_dir($torrentSavePath)) {
            do_log(sprintf("torrentSavePath: %s not exists", $torrentSavePath), 'error');
            throw new NexusException(nexus_trans('upload.torrent_save_dir_not_exists'));
        }
        if (!is_writable($torrentSavePath)) {
            do_log(sprintf("torrentSavePath: %s not writable", $torrentSavePath), 'error');
            throw new NexusException(nexus_trans('upload.torrent_save_dir_not_writable'));
        }
        return $torrentSavePath;
    }

    /** @param  mixed  $torrentId */
    private function sendReward($torrentId): void
    {
        $user = Auth::user();
        $old = $user->seedbonus;
        $delta = \App\Support\Config\SiteConfig::current()->bonus->uploadTorrent();
        if ($delta > 0) {
            $new = $old + $delta;
            $user->increment('seedbonus', $delta);
            BonusLogs::add($user->id, $old, $delta, $new, "Upload torrent: $torrentId", BonusLogs::BUSINESS_TYPE_UPLOAD_TORRENT);
            do_log("upload torrent: $torrentId, success send reward: $delta");
        } else {
            do_log("upload torrent: $torrentId, no reward");
        }
    }

    /**
     * @param  \App\Models\Torrent  $torrent
     * @param  mixed  $userId
     */
    public function sendEmailNotification(Torrent $torrent, $userId = 0): int
    {
        $logMsg = sprintf("torrent: %s, category: %s", $torrent->id, $torrent->category);
        $siteConfig = \App\Support\Config\SiteConfig::current();
        if (!$siteConfig->smtp->emailNotify() || $siteConfig->smtp->type() == 'none') {
            do_log("$logMsg, not allow user receive email notification or smtp type is none");
            return 0;
        }
        $page = 1;
        $size = 1000;
        $query = User::query()
            ->where("notifs", "like", "%[cat$torrent->category]%")
            ->where("notifs", "like","%[email]%")
            ->normal()
        ;
        if ($userId > 0) {
            $query->where("id", $userId);
        }
        $total = (clone $query)->count();
        if ($total == 0) {
            do_log(sprintf("%s, no user receive email notification", $logMsg));
            return 0;
        }
        $toolRep = new ToolRepository();
        $categoryName = $torrent->basic_category->name;
        $torrentUploader = $torrent->user;
        $successCount = 0;
        while (true) {
            $logPage = "$logMsg, page: $page";
            $users = (clone $query)->with(['language'])->forPage($page, $size)->get(['id', 'email', 'lang']);
            if ($users->isEmpty()) {
                do_log(sprintf("%s, no more user", $logPage));
                break;
            }
            foreach ($users as $user) {
                $locale = $user->locale;
                $logUser = "$logPage, user $user->id, locale: $locale";
                $subject = nexus_trans("upload.email_notification_subject", [
                    'site_name' => \App\Support\Config\SiteConfig::current()->basic->siteName()
                ], $locale);
                $body = nexus_trans("upload.email_notification_body", [
                    'site_name' => \App\Support\Config\SiteConfig::current()->basic->siteName(),
                    'name' => $torrent->name,
                    'size' => \App\Support\Format::size($torrent->size),
                    'category' => $categoryName,
                    'upload_by' => $this->handleAnonymous($torrentUploader->username, $torrentUploader, $user, $torrent),
                    'description' => Str::limit(strip_tags(format_comment($torrent->extra->descr)), 500),
                    'torrent_url' => sprintf("%s/details.php?id=%s&hit=1", getBaseUrl(), $torrent->id),
                ], $locale);
                $sendResult = $toolRep->sendMail($user->email, $subject, $body);
                do_log(sprintf("%s, send result: %s", $logUser, $sendResult));
                if ($sendResult) {
                    $successCount++;
                }
            }
            $page++;
        }
        do_log("$logMsg, receive email notification user total: $total, successCount: $successCount, done!");
        return $successCount;
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Category  $category
     */
    public function saveCustomFields(Request $request, Category $category, int $torrentId): void
    {
        if (!$request->has("custom_fields")) {
            return;
        }
        $data = $request->input("custom_fields.{$category->mode}", []);
        if (empty($data)) {
            return;
        }
        $field = new \Nexus\Field\Field();
        $field->saveFieldValues($category->mode, $torrentId, $data);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Torrent  $torrent
     */
    private function handleOffer(Request $request, Torrent $torrent, User $user): void
    {
        $offerId = (int) $request->offer;
        if ($offerId <= 0) {
            return;
        }
        if (!TorrentUploadRepository::isAllowedOffer($offerId, $user->id)) {
            return;
        }

        $voterIds = TorrentUploadRepository::getOfferVoterIds($offerId, $user->id);
        foreach ($voterIds as $voterId) {
            $locale = Locale::userLocale($voterId);
            $msg = nexus_trans("torrent.msg_offer_you_voted", [], $locale)
                . $torrent->name
                . nexus_trans("torrent.msg_was_uploaded_by", [], $locale)
                . $user->username
                . nexus_trans("torrent.msg_you_can_download", [], $locale)
                . "[url=" . Url::schemeAndHost() . "/details.php?id=" . $torrent->id . "&hit=1]"
                . nexus_trans("torrent.msg_here", [], $locale)
                . "[/url]";
            $subject = nexus_trans("torrent.msg_offer", [], $locale)
                . $torrent->name
                . nexus_trans("torrent.msg_was_just_uploaded", [], $locale);
            Message::add([
                'sender' => 0,
                'subject' => $subject,
                'receiver' => $voterId,
                'added' => now()->toDateTimeString(),
                'msg' => $msg,
            ]);
        }
        TorrentUploadRepository::finalizeOffer($offerId, $user->id);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
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
     * @param  \Illuminate\Http\Request  $request
     * @return  array<int, mixed>
     */
    private function getTags(Request $request, int $mode): array
    {
        if ($request->has("tags.{$mode}")) {
            $tags = $request->input("tags.{$mode}", []);
            return is_array($tags) ? $tags : [];
        }

        $tags = $request->tags ?: [];
        if (!is_array($tags)) {
            $tags = explode(',', $tags);
        }
        return $tags;
    }

}
