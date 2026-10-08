<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Contracts\Repositories\TorrentRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Enums\Permission\PermissionEnum;
use App\Http\Requests\MagicRewardRequest;
use App\Models\BonusLogs;
use App\Models\User;
use App\Repositories\BonusCalculationRepository;
use App\Repositories\UserListingRepository;
use App\Services\MagicRewardService;
use App\Support\Api;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\CurrentUser;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BonusHistoryController extends BasePageController
{
    private BonusCalculationRepository $bonusCalculationRepository;

    public function __construct(private readonly TorrentRepositoryInterface $torrentRepository, private readonly UserListingRepository $userListingRepository, private readonly UserRepositoryInterface $userRepository,
        BonusCalculationRepository $bonusCalculationRepository,
        private readonly CurrentUser $currentUser,
    ) {
        $this->bonusCalculationRepository = $bonusCalculationRepository;
    }

    public function bonusLog(Request $request): View|RedirectResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $uid = (int) (request()->input('uid') ?? $this->currentUser->id());

        if (! Validators::isId($uid)) {
            return $this->abortResponse('Error', 'Invalid uid.');
        }

        $user = $this->userRepository->findById((int) $uid, User::$commonFields);
        if (! $user) {
            return $this->abortResponse('Error', "Invalid uid: {$uid}");
        }

        if ($uid != ($this->currentUser->id())) {
            $allowed = Permission::can(PermissionEnum::VIEW_USER_HISTORY, $user);
            if (! $allowed) {
                return $this->abortResponse('Error', 'Permission denied.');
            }
        }

        $defaultCategory = BonusLogs::CATEGORY_COMMON;
        $category = request()->input('category') ?? $defaultCategory;
        $categoryOptions = BonusLogs::listCategoryOptions();
        if (! isset($categoryOptions[$category])) {
            return $this->abortResponse('Error', "Invalid category: {$category}");
        }

        $businessType = (int) (request()->input('business_type') ?? 0);
        $businessTypeOptions = BonusLogs::listBusinessTypeOptions($defaultCategory);
        if ($businessType && ! isset($businessTypeOptions[$businessType])) {
            return $this->abortResponse('Error', "Invalid business_type: {$businessType}");
        }

        $title = Locale::trans('bonus-log.title_for_user', [], null);
        $pagerParam = "?uid={$uid}&category={$category}&business_type={$businessType}";
        $textSelectOnePlease = Locale::trans('nexus.select_one_please', [], null);
        $resetText = Locale::trans('label.reset', [], null);
        $submitText = Locale::trans('label.submit', [], null);
        $categoryText = Locale::trans('bonus-log.category', [], null);
        $businessTypeText = Locale::trans('bonus-log.fields.business_type', [], null);

        $categoryOptionList = [];
        foreach ($categoryOptions as $name => $text) {
            $categoryOptionList[] = [
                'value' => (string) $name,
                'label' => $text,
                'selected' => (request()->input('category') ?? '') == $name,
            ];
        }

        $businessTypeOptionList = [];
        foreach ($businessTypeOptions as $name => $text) {
            $businessTypeOptionList[] = [
                'value' => (string) $name,
                'label' => $text,
                'selected' => (request()->input('business_type') ?? '') == $name,
            ];
        }

        $rep = $this->bonusCalculationRepository;
        $total = $rep->getCount($category, $uid, $businessType);
        [$pagertop, $pagerbottom, , , $pageSize, $page] = Pagination::pager(50, $total, "{$pagerParam}&");
        $list = $rep->getList($category, $uid, $businessType, $page + 1, $pageSize);

        $rows = [];
        foreach ($list as $row) {
            $old = (float) $row->old_total_value;
            $new = (float) $row->new_total_value;
            $value = (float) $row->value;
            $rows[] = [
                'businessTypeText' => $row->businessTypeText,
                'old_formatted' => $old > 0 ? number_format($old, 1) : '-',
                'value_formatted' => ($old < $new ? '+' : '-').number_format($value, 1),
                'new_formatted' => $new > 0 ? number_format($new, 1) : '-',
                'comment' => $row->comment ?? '',
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        $resetJs = <<<'JS'
document.getElementById("reset").addEventListener('click', function () {
    var cat = document.querySelector("select[name=category]")
    var biz = document.querySelector("select[name=business_type]")
    if (cat) cat.value = ''
    if (biz) biz.value = ''
})
JS;
        AssetAppender::js($resetJs, 'footer', false);

        return $this->renderPage($request, 'bonus-log', true, [
            'title' => $title,
            'uid' => $uid,
            'username' => $user->username,
            'category' => $category,
            'businessType' => $businessType,
            'categoryText' => $categoryText,
            'businessTypeText' => $businessTypeText,
            'textSelectOnePlease' => $textSelectOnePlease,
            'resetText' => $resetText,
            'submitText' => $submitText,
            'categoryOptionList' => $categoryOptionList,
            'businessTypeOptionList' => $businessTypeOptionList,
            'pagerParam' => $pagerParam,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
            'requestUri' => (string) request()->server->get('REQUEST_URI', ''),
            'columnBusinessTypeLabel' => Locale::trans('bonus-log.fields.business_type', [], null),
            'columnOldTotalLabel' => Locale::trans('bonus-log.fields.old_total_value', [], null),
            'columnValueLabel' => Locale::trans('bonus-log.fields.value', [], null),
            'columnNewTotalLabel' => Locale::trans('bonus-log.fields.new_total_value', [], null),
            'columnCommentLabel' => Locale::trans('label.comment', [], null),
            'columnCreatedAtLabel' => Locale::trans('label.created_at', [], null),
        ]);

    }

    public function uploaders(Request $request): View|RedirectResponse|Response
    {
        $uploaderClass = defined('UC_UPLOADER') ? \constant('UC_UPLOADER') : PHP_INT_MAX;
        if (UserDisplay::currentClass() < $uploaderClass) {
            return $this->abortResponse('Error', 'Permission denied.');
        }

        $year = (int) (request()->query('year') ?? 0);
        if (! $year || $year < 2000) {
            $year = (int) date('Y');
        }
        $month = (int) (request()->query('month') ?? 0);
        if (! $month || $month <= 0 || $month > 12) {
            $month = (int) date('m');
        }
        $order = (string) (request()->query('order') ?? '');
        if (! in_array($order, ['username', 'torrent_size', 'torrent_count'])) {
            $order = 'username';
        }

        $sortColumn = match ($order) {
            'torrent_size' => DB::raw('SUM(torrents.size)'),
            'torrent_count' => DB::raw('COUNT(torrents.id)'),
            default => 'users.username',
        };
        $sortDirection = $order === 'username' ? 'asc' : 'desc';

        $dateFounded = SiteConfig::current()->tweak->dateFounded('2010-08-19');
        $yearFounded = (int) substr($dateFounded, 0, 4);
        if (! $yearFounded) {
            $yearFounded = 2007;
        }
        $yearNow = (int) date('Y');

        $timeStart = strtotime("{$year}-{$month}-01 00:00:00") ?: time();
        $sqlStartTime = date('Y-m-d H:i:s', $timeStart);
        $timeEnd = strtotime('+1 month', $timeStart) ?: time();
        $sqlEndTime = date('Y-m-d H:i:s', $timeEnd);

        $uploaders = $this->torrentRepository->listUploaderStats($sqlStartTime, $sqlEndTime, $uploaderClass, $sortColumn, $sortDirection);

        $hasUpUserIds = [];
        foreach ($uploaders as $uploader) {
            $hasUpUserIds[] = (int) ((array) $uploader)['userid'];
        }
        $nonUploaderQuery = $this->userListingRepository->listAboveClassExcluding($uploaderClass, $hasUpUserIds);

        $allUserIds = array_merge($hasUpUserIds, $nonUploaderQuery->pluck('userid')->map(fn ($id) => (int) $id)->all());
        UserDisplay::preload($allUserIds);
        $lastTorrents = $allUserIds === []
            ? collect()
            : $this->torrentRepository->listLastTorrentsForOwners($allUserIds);

        $rows = [];
        foreach ($uploaders as $uploader) {
            $row = (array) $uploader;
            $last = (array) ($lastTorrents->get((int) $row['userid']) ?? []);
            $rows[] = [
                'userid' => (int) $row['userid'],
                'username' => $row['username'],
                'torrent_size' => (float) ($row['torrent_size'] ?? 0),
                'torrent_count' => (int) ($row['torrent_count'] ?? 0),
                'last_added' => $last['added'] ?? '',
                'last_id' => (int) ($last['id'] ?? 0),
                'last_name' => $last['name'] ?? '',
            ];
        }

        foreach ($nonUploaderQuery as $nonUploader) {
            $row = (array) $nonUploader->getAttributes();
            $last = (array) ($lastTorrents->get((int) $row['userid']) ?? []);
            $rows[] = [
                'userid' => (int) $row['userid'],
                'username' => $row['username'],
                'torrent_size' => 0,
                'torrent_count' => 0,
                'last_added' => $last['added'] ?? '',
                'last_id' => (int) ($last['id'] ?? 0),
                'last_name' => $last['name'] ?? '',
            ];
        }

        $naText = __('uploaders.text_not_available');
        foreach ($rows as &$row) {
            $row['usernameHtml'] = SafeHtml::fromTrustedHtml((string) UserDisplay::username($row['userid'], false, true, true, false, false, true));
            $row['sizeFormatted'] = $row['torrent_size'] ? Format::size($row['torrent_size']) : '0';
        }
        unset($row);

        $yearOptionList = [];
        for ($i = $yearFounded; $i <= $yearNow; $i++) {
            $yearOptionList[] = ['value' => $i, 'selected' => $i == $year];
        }
        $monthOptionList = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthOptionList[] = ['value' => $i, 'selected' => $i == $month];
        }

        return $this->renderPage($request, 'uploaders', true, [
            'year' => $year,
            'month' => $month,
            'order' => $order,
            'yearOptions' => $yearOptionList,
            'monthOptions' => $monthOptionList,
            'datefounded' => $dateFounded,
            'timeStart' => $timeStart,
            'naText' => $naText,
            'rows' => $rows,
        ]);

    }

    public function magicSubmit(MagicRewardRequest $request, MagicRewardService $magicRewardService): JsonResponse|Response
    {
        $curUser = $this->currentUser->get() ?? [];
        $validated = $request->validated();
        $torrentId = (int) $validated['id'];
        $value = (int) abs((float) $validated['value']);

        $user = Auth::guard('nexus-web')->user();
        if (! $user instanceof User) {
            return response()->json(Api::failWithContext('Invalid torrent owner!', $validated));
        }

        try {
            $magicRewardService->give($user, $torrentId, $value);
        } catch (\LogicException $e) {
            return response()->json(Api::failWithContext($e->getMessage(), $validated));
        }

        return response()->json(Api::successWithContext('OK', $validated));

    }
}
