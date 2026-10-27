<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\Auth\ActorContext;
use App\Enums\HitAndRunStatus;
use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Snatch;
use App\Repositories\HitAndRunRepository;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Ratio;
use App\Support\Time;
use App\ViewModels\HitAndRun\HitAndRunPageViewModel;
use App\ViewModels\HitAndRun\HitAndRunRowViewModel;
use Carbon\Carbon;
use Carbon\CarbonInterval;

final class HitAndRunPageService
{
    public function __construct(
        private readonly ActorContext $actor,
        private readonly HitAndRunRepository $hitAndRunRepository,
    ) {}

    /**
     * @param  iterable<HitAndRun>  $rows
     */
    public function build(int $userId, int $viewerId, iterable $rows): HitAndRunPageViewModel
    {
        $browseCat = SiteConfig::current()->main->browseCat();
        $seedTimeMinimum = $this->numericConfig(HitAndRun::getConfig('seed_time_minimum', $browseCat));
        $ignoreWhenRatioReach = $this->numericConfig(HitAndRun::getConfig('ignore_when_ratio_reach', $browseCat));
        $inspectTime = $this->numericConfig(HitAndRun::getConfig('inspect_time', $browseCat));
        $banUserWhenCountsReach = $this->numericConfig(HitAndRun::getConfig('ban_user_when_counts_reach', $browseCat));
        $price = BonusLogs::getBonusForCancelHitAndRun();
        $balance = $this->actor->seedbonus();
        $viewRows = [];

        foreach ($rows as $row) {
            $snatch = $row->snatch;
            $uploaded = $snatch->uploaded ?? 0;
            $downloaded = $snatch->downloaded ?? 0;
            $ratio = trim(strip_tags(Ratio::hr($uploaded, $downloaded)->toHtml()));
            $numericRatio = Ratio::share($uploaded, $downloaded);
            $status = (int) $row->status;
            $seedDone = null;
            $seedRequired = null;

            if (
                $status === HitAndRunStatus::INSPECTING->value
                && $snatch instanceof Snatch
                && $seedTimeMinimum !== null
                && $seedTimeMinimum > 0
            ) {
                $seedDone = (int) $snatch->seedtime;
                $seedRequired = $seedTimeMinimum * 3600;
            }

            $inspectEndsIn = null;
            if ($status === HitAndRunStatus::INSPECTING->value && $inspectTime !== null) {
                $inspectEndsAt = Carbon::parse((string) $row->created_at)
                    ->addSeconds((int) round($inspectTime * 3600));
                $inspectEndsIn = CarbonInterval::seconds($inspectEndsAt->getTimestamp() - now()->getTimestamp())
                    ->cascade()
                    ->forHumans(['parts' => 2, 'short' => true]);
            }

            $canRemove = $row->uid === $viewerId
                && in_array($status, HitAndRun::CAN_PARDON_STATUS, true);
            $removeDisabled = $balance < $price;

            $viewRows[] = new HitAndRunRowViewModel(
                id: (int) $row->id,
                torrentId: (int) $row->torrent_id,
                torrentName: (string) ($row->torrent->name ?? ''),
                uploaded: Format::size($uploaded),
                downloaded: Format::size($downloaded),
                ratio: $ratio,
                ratioLow: $ignoreWhenRatioReach !== null
                    && is_numeric($numericRatio)
                    && (float) $numericRatio < $ignoreWhenRatioReach,
                completedAt: Time::formatDateTime($snatch?->completedat),
                status: $status,
                statusText: Locale::trans('hr.status_'.$status, [], null),
                comment: (string) $row->comment,
                seedDone: $seedDone,
                seedRequired: $seedRequired,
                inspectEndsIn: $inspectEndsIn,
                canRemove: $canRemove,
                removeDisabled: $removeDisabled,
                pointsShort: $removeDisabled ? (int) ceil($price - $balance) : null,
            );
        }

        return new HitAndRunPageViewModel(
            isEnabled: HitAndRun::getIsEnabled(),
            price: $price,
            balance: $balance,
            statusCounts: $this->hitAndRunRepository->countsByStatusForUser($userId),
            rows: $viewRows,
            seedTimeMinimum: $seedTimeMinimum,
            ignoreWhenRatioReach: $ignoreWhenRatioReach,
            inspectTime: $inspectTime,
            banUserWhenCountsReach: $banUserWhenCountsReach,
        );
    }

    private function numericConfig(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
