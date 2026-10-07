<?php

declare(strict_types=1);

namespace App\Services\Offer;

use App\Auth\Permission;
use App\Contracts\Repositories\OfferCommentRepositoryInterface;
use App\Contracts\Repositories\OfferRepositoryInterface;
use App\Contracts\Repositories\OfferVoteRepositoryInterface;
use App\Enums\OfferAllowed;
use App\Enums\Permission\PermissionEnum;
use App\Enums\UserTimeType;
use App\Support\Cache\NexusCache;
use App\Support\Format;
use App\Support\Html\SafeHtml;
use App\Support\LegacyResponse;
use App\Support\Pagination;
use App\Support\Time;
use App\Support\UserDisplay;
use App\ViewModels\Offer\OfferAllowedBadge;
use App\ViewModels\Offer\OfferDetailsViewModel;
use Illuminate\Http\Request;

/**
 * Builds the single-offer details section (badges, votes, pager, descr).
 */
final class OfferDetailsBuilder
{
    public function __construct(
        private readonly OfferRepositoryInterface $offerRepository,
        private readonly OfferVoteRepositoryInterface $offerVoteRepository,
        private readonly OfferCommentRepositoryInterface $offerCommentRepository,
        private readonly NexusCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $curUser
     */
    public function build(array $curUser, int $userId, Request $request): OfferDetailsViewModel
    {
        $id = (int) $request->query('id', 0);
        if (! $id) {
            LegacyResponse::abort(__('offers.std_error'), __('offers.std_smell_rat'));
        }

        $offer = $this->offerRepository->findOffer($id);
        if (! $offer) {
            LegacyResponse::abort(__('offers.std_error'), __('offers.text_nothing_found'));
        }
        $num = $offer->toArray();

        $timeFormat = Time::format((string) ($num['added'] ?? ''), true, false);
        $offertime = ($curUser['timetype'] ?? 1) !== UserTimeType::TIMEALIVE->value
            ? __('offers.text_at').$timeFormat
            : __('offers.text_blank').$timeFormat;

        $status = match ((int) ($num['allowed'] ?? 1)) {
            OfferAllowed::PENDING->value => new OfferAllowedBadge(__('offers.text_pending'), 'nx-color-red'),
            OfferAllowed::ALLOWED->value => new OfferAllowedBadge(__('offers.text_allowed'), 'nx-color-green'),
            default => new OfferAllowedBadge(__('offers.text_denied'), 'nx-color-red'),
        };

        $voteCounts = $this->offerVoteRepository->getVoteCounts($id);
        $yeah = (int) $voteCounts['yeah'];
        $against = (int) $voteCounts['against'];

        $isPending = (int) ($num['allowed'] ?? 1) === OfferAllowed::PENDING->value;
        $allowed = (int) ($num['allowed'] ?? 1) === OfferAllowed::ALLOWED->value;

        $allowedNote = '';
        if ($allowed && $userId !== (int) ($num['userid'] ?? 0)) {
            $allowedNote = __('offers.text_voter_receives_pm_note');
        }
        if ($allowed && $userId === (int) ($num['userid'] ?? 0)) {
            $allowedNote = __('offers.text_urge_upload_offer_note');
        }

        $description = '';
        if (! empty($num['descr'])) {
            $descrKey = 'fmt_offer_'.md5((string) $num['descr']);
            $cachedDescr = $this->cache->get($descrKey);
            if (is_string($cachedDescr)) {
                $description = SafeHtml::fromTrustedHtml($cachedDescr);
            } else {
                $description = Format::formatComment((string) $num['descr']);
                $this->cache->put($descrKey, (string) $description, 86400);
            }
        }

        // Comments section
        $commentCount = $this->offerCommentRepository->countComments($id);

        $pagerTop = '';
        $pagerBottom = '';
        if ($commentCount) {
            [$pagerTop, $pagerBottom] = Pagination::pager(10, $commentCount, "/web/offers?id={$id}&off_details=1&", ['lastpagedefault' => 1]);
        }

        return new OfferDetailsViewModel(
            id: $id,
            name: (string) ($num['name'] ?? ''),
            offeredBy: UserDisplay::username((int) ($num['userid'] ?? 0)),
            offerTime: SafeHtml::fromTrustedHtml($offertime),
            status: $status,
            showAllowRow: Permission::can(PermissionEnum::OFFER_MANAGE) && $isPending,
            isPending: $isPending,
            canAgainst: Permission::can(PermissionEnum::AGAINST_OFFER),
            yeah: $yeah,
            against: $against,
            allowedNote: $allowedNote,
            showEditDelete: $userId === (int) ($num['userid'] ?? 0) || Permission::can(PermissionEnum::OFFER_MANAGE),
            description: SafeHtml::fromTrustedHtml($description),
            commentCount: $commentCount,
            pagerTop: SafeHtml::fromTrustedHtml($pagerTop),
            pagerBottom: SafeHtml::fromTrustedHtml($pagerBottom),
        );
    }
}
