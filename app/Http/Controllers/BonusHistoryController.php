<?php

namespace App\Http\Controllers;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Models\BonusLogs;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\BonusRepository;
use App\Support\Api;
use App\Support\Bonus;
use App\Support\CurrentUser;
use App\Support\Globals;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\UserDisplay;
use App\Support\Validators;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Nexus\Nexus;

class BonusHistoryController extends LegacyController
{
    private BonusRepository $bonusRepository;

    public function __construct(BonusRepository $bonusRepository)
    {
        $this->bonusRepository = $bonusRepository;
    }

    public function bonusLog(Request $request): View|RedirectResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $uid = (int) (request()->input('uid') ?? $curUser['id'] ?? 0);

        if (! Validators::isId($uid)) {
            return $this->legacyAbortResponse('Error', 'Invalid uid.');
        }

        $user = User::query()->where('id', $uid)->first(User::$commonFields);
        if (! $user) {
            return $this->legacyAbortResponse('Error', "Invalid uid: {$uid}");
        }

        if ($uid != ($curUser['id'] ?? 0)) {
            $allowed = Permission::can(PermissionEnum::VIEW_USER_HISTORY, $user);
            if (! $allowed) {
                return $this->legacyAbortResponse('Error', 'Permission denied.');
            }
        }

        $isRecordSeedingBonusLog = Setting::getIsRecordSeedingBonusLog();
        $defaultCategory = BonusLogs::CATEGORY_COMMON;
        $category = request()->input('category') ?? $defaultCategory;
        $categoryOptions = BonusLogs::listCategoryOptions($isRecordSeedingBonusLog);
        if (! isset($categoryOptions[$category])) {
            return $this->legacyAbortResponse('Error', "Invalid category: {$category}");
        }

        $businessType = (int) (request()->input('business_type') ?? 0);
        $businessTypeOptions = BonusLogs::listBusinessTypeOptions($isRecordSeedingBonusLog ? '' : $defaultCategory);
        if ($businessType && ! isset($businessTypeOptions[$businessType])) {
            return $this->legacyAbortResponse('Error', "Invalid business_type: {$businessType}");
        }

        $title = Locale::trans('bonus-log.title_for_user', [], null);
        $pagerParam = "?uid={$uid}&category={$category}&business_type={$businessType}";
        $textSelectOnePlease = Locale::trans('nexus.select_one_please', [], null);
        $resetText = Locale::trans('label.reset', [], null);
        $submitText = Locale::trans('label.submit', [], null);
        $categoryText = Locale::trans('bonus-log.category', [], null);
        $businessTypeText = Locale::trans('bonus-log.fields.business_type', [], null);

        $categoryOptionsHtml = '';
        foreach ($categoryOptions as $name => $text) {
            $selected = (request()->input('category') ?? '') == $name ? ' selected' : '';
            $categoryOptionsHtml .= sprintf('<option value="%s"%s>%s</option>', htmlspecialchars((string) $name), $selected, htmlspecialchars($text));
        }

        $businessTypeOptionsHtml = '';
        foreach ($businessTypeOptions as $name => $text) {
            $selected = (request()->input('business_type') ?? '') == $name ? ' selected' : '';
            $businessTypeOptionsHtml .= sprintf('<option value="%s"%s>%s</option>', htmlspecialchars((string) $name), $selected, htmlspecialchars($text));
        }

        $rep = $this->bonusRepository;
        $total = $rep->getCount($category, $uid, $businessType);
        [$pagertop, $pagerbottom, , , $pageSize, $page] = Pagination::pager(50, $total, "{$pagerParam}&");
        $list = $rep->getList($category, $uid, $businessType, $page + 1, $pageSize);

        $rows = [];
        foreach ($list as $row) {
            $r = (array) $row;
            $old = (float) ($r['old_total_value'] ?? 0);
            $new = (float) ($r['new_total_value'] ?? 0);
            $value = (float) ($r['value'] ?? 0);
            $rows[] = [
                'businessTypeText' => $r['businessTypeText'] ?? '',
                'old_formatted' => $old > 0 ? number_format($old, 1) : '-',
                'value_formatted' => ($old < $new ? '+' : '-').number_format($value, 1),
                'new_formatted' => $new > 0 ? number_format($new, 1) : '-',
                'comment' => $r['comment'] ?? '',
                'created_at' => $r['created_at'] ?? '',
            ];
        }

        $resetJs = <<<'JS'
jQuery("#reset").on('click', function () {
    jQuery("select[name=category]").val('')
    jQuery("select[name=business_type]").val('')
})
JS;
        Nexus::js($resetJs, 'footer', false);

        return $this->legacyPage($request, 'bonus-log', true, [
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
            'categoryOptionsHtml' => $categoryOptionsHtml,
            'businessTypeOptionsHtml' => $businessTypeOptionsHtml,
            'pagerParam' => $pagerParam,
            'pagertop' => $pagertop,
            'pagerbottom' => $pagerbottom,
            'rows' => $rows,
        ]);

    }

    public function uploaders(Request $request): View|RedirectResponse|Response
    {
        $uploaderClass = defined('UC_UPLOADER') ? \constant('UC_UPLOADER') : PHP_INT_MAX;
        if (UserDisplay::currentClass() < $uploaderClass) {
            return $this->legacyAbortResponse('Error', 'Permission denied.');
        }

        $langUploaders = (array) app(Globals::class)->get('lang_uploaders', []);

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
        $sortDirection = $order === 'username' ? 'ASC' : 'DESC';

        $dateFounded = (string) app(Globals::class)->get('datefounded', '2010-08-19');
        $yearFounded = (int) substr($dateFounded, 0, 4);
        if (! $yearFounded) {
            $yearFounded = 2007;
        }
        $yearNow = (int) date('Y');

        $timeStart = strtotime("{$year}-{$month}-01 00:00:00") ?: time();
        $sqlStartTime = date('Y-m-d H:i:s', $timeStart);
        $timeEnd = strtotime('+1 month', $timeStart) ?: time();
        $sqlEndTime = date('Y-m-d H:i:s', $timeEnd);

        $uploaders = DB::table('torrents')
            ->leftJoin('users', 'torrents.owner', '=', 'users.id')
            ->where('users.class', '>=', $uploaderClass)
            ->where('torrents.added', '>', $sqlStartTime)
            ->where('torrents.added', '<', $sqlEndTime)
            ->groupBy('users.id', 'users.username')
            ->orderBy($sortColumn, $sortDirection)
            ->get([
                'users.id AS userid',
                'users.username AS username',
                DB::raw('COUNT(torrents.id) AS torrent_count'),
                DB::raw('SUM(torrents.size) AS torrent_size'),
            ]);

        $hasUpUserIds = [];
        $rows = [];
        foreach ($uploaders as $uploader) {
            $row = (array) $uploader;
            $lastTorrent = DB::table('torrents')
                ->where('owner', (int) $row['userid'])
                ->orderByDesc('id')
                ->first(['id', 'name', 'added']);
            $last = $lastTorrent ? (array) $lastTorrent : [];
            $rows[] = [
                'userid' => (int) $row['userid'],
                'username' => $row['username'],
                'torrent_size' => (float) ($row['torrent_size'] ?? 0),
                'torrent_count' => (int) ($row['torrent_count'] ?? 0),
                'last_added' => $last['added'] ?? '',
                'last_id' => (int) ($last['id'] ?? 0),
                'last_name' => $last['name'] ?? '',
            ];
            $hasUpUserIds[] = (int) $row['userid'];
        }

        $nonUploaderQuery = User::query()
            ->where('class', '>=', $uploaderClass)
            ->when(! empty($hasUpUserIds), function ($q) use ($hasUpUserIds) {
                $q->whereNotIn('id', $hasUpUserIds);
            })
            ->orderBy('username')
            ->get(['id AS userid', 'username']);

        foreach ($nonUploaderQuery as $nonUploader) {
            $row = (array) $nonUploader->getAttributes();
            $lastTorrent = DB::table('torrents')
                ->where('owner', (int) $row['userid'])
                ->orderByDesc('id')
                ->first(['id', 'name', 'added']);
            $last = $lastTorrent ? (array) $lastTorrent : [];
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

        $yearOptions = '';
        for ($i = $yearFounded; $i <= $yearNow; $i++) {
            $selected = $i == $year ? ' selected="selected"' : '';
            $yearOptions .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }
        $monthOptions = '';
        for ($i = 1; $i <= 12; $i++) {
            $selected = $i == $month ? ' selected="selected"' : '';
            $monthOptions .= '<option value="'.$i.'"'.$selected.'>'.$i.'</option>';
        }

        return $this->legacyPage($request, 'uploaders', true, [
            'lang_uploaders' => $langUploaders,
            'year' => $year,
            'month' => $month,
            'order' => $order,
            'yearOptions' => $yearOptions,
            'monthOptions' => $monthOptions,
            'datefounded' => $dateFounded,
            'timeStart' => $timeStart,
            'rows' => $rows,
        ]);

    }

    public function magic(Request $request): JsonResponse|Response
    {
        $curUser = app(CurrentUser::class)->get() ?? [];
        $userId = (int) ($curUser['id'] ?? 0);
        $torrentId = (int) ($request->input('id') ?? 0);
        $value = (int) abs((float) ($request->input('value') ?? 0));

        if (! in_array($value, Setting::getBonusRewardOptions())) {
            return response()->json(Api::failWithContext('Invalid value.', $request->all()));
        }
        if ($value > (float) ($curUser['seedbonus'] ?? 0)) {
            return response()->json(Api::failWithContext('You do not have such bonus!', $request->all()));
        }

        $torrentOwner = Torrent::query()->where('id', $torrentId)->value('owner');
        if (! $torrentOwner) {
            return response()->json(Api::failWithContext('Invalid torrent id!', $request->all()));
        }
        if ((int) $torrentOwner === $userId) {
            return response()->json(Api::failWithContext('You are giving magic to yourself.', $request->all()));
        }

        $alreadyMagic = DB::table('magic')->where('torrentid', $torrentId)->where('userid', $userId)->count();
        if ($alreadyMagic != 0) {
            return response()->json(Api::failWithContext('You already gave the magic value!', $request->all()));
        }

        $todayStr = now()->startOfDay();
        $todayCount = Reward::query()->where('userid', $userId)->where('created_at', '>=', $todayStr)->count();
        $timesLimit = Setting::getBonusRewardTimesLimit();
        if ($timesLimit > 0 && $todayCount >= $timesLimit) {
            return response()->json(Api::failWithContext('You already reach times limit!', $request->all()));
        }

        $torrentOwnerInfo = User::query()->find($torrentOwner, User::$commonFields);
        if (! $torrentOwnerInfo) {
            return response()->json(Api::failWithContext('Invalid torrent owner!', $request->all()));
        }

        DB::table('magic')->insert([
            'torrentid' => $torrentId,
            'userid' => $userId,
            'value' => $value,
        ]);

        Bonus::updatePoints('-', (float) $value, $userId);
        BonusLogs::add($userId, (float) ($curUser['seedbonus'] ?? 0), $value, (float) ($curUser['seedbonus'] ?? 0) - $value, '', BonusLogs::BUSINESS_TYPE_REWARD_TORRENT);

        Bonus::updatePoints('+', (float) $value, (int) $torrentOwner);
        BonusLogs::add((int) $torrentOwnerInfo['id'], (float) $torrentOwnerInfo['seedbonus'], $value, (float) $torrentOwnerInfo['seedbonus'] + $value, '', BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD);

        return response()->json(Api::successWithContext('OK', $request->all()));

    }
}
