<?php

declare(strict_types=1);

namespace App\ViewModels\Torrent;

use App\Auth\Permission;
use App\Enums\Permission\PermissionEnum;
use App\Enums\TorrentApprovalStatus;
use App\Enums\TorrentType;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Repositories\SearchBoxSchemaBuilder;
use App\Repositories\TorrentModerationRepository;
use App\Support\AssetAppender;
use App\Support\Config\SiteConfig;
use App\Support\Html\SafeHtml;
use App\Support\Locale;
use App\Support\Promotion;
use App\Support\Strings;
use App\Support\Time;
use App\Support\TorrentAccess;
use App\Support\UserDisplay;
use App\Support\YesNo;

/**
 * Assembles {@see TorrentDetailsViewModel} — the presentation assembly
 * that used to build `*Html` strings inside TorrentDetailsController.
 */
final class TorrentDetailsViewFactory
{
    public function __construct(
        private readonly TorrentModerationRepository $moderationRepository,
        private readonly SearchBoxSchemaBuilder $searchBoxSchemaBuilder,
    ) {}

    /**
     * @param  array<int|string, mixed>  $row
     * @param  array<int|string, mixed>  $currentUser
     * @param  array<string, mixed>  $requestFlags
     */
    public function build(
        int $id,
        array $row,
        array $currentUser,
        ?TorrentOperationLog $denyLog,
        bool $hasBuy,
        array $requestFlags,
    ): TorrentDetailsViewModel {
        $isOwner = (int) ($currentUser['id'] ?? 0) === (int) ($row['owner'] ?? 0);
        $owned = Permission::can(PermissionEnum::TORRENT_MANAGE) || $isOwner;
        $downloadAllowed = $isOwner || ! YesNo::isNo($currentUser['downloadpos'] ?? null);

        return new TorrentDetailsViewModel(
            title: $this->buildTitle($row),
            owner: $this->buildOwner($row, $isOwner),
            taxonomy: $this->buildTaxonomy($row),
            actions: $this->buildActions($id, $row, $hasBuy, $owned, $downloadAllowed, (string) ($requestFlags['returnto'] ?? '')),
            info: $this->buildInfoRow($id, $row),
            hotMeter: $this->buildHotMeter($id, $row),
            peers: new PeersRow($id, (int) $row['seeders'], (int) $row['leechers']),
            denyBanner: $this->buildDenyBanner($row, $denyLog),
            uploadTimePrefix: ($currentUser['timetype'] ?? '') !== 'timealive'
                ? (string) __('legacy/details.text_at')
                : (string) __('legacy/details.text_blank'),
            uploadTime: ($currentUser['timetype'] ?? '') !== 'timealive'
                ? SafeHtml::fromTrustedHtml((string) $row['added'])
                : SafeHtml::fromTrustedHtml((string) Time::format((string) $row['added'], true, false)),
            showOrHideTitle: self::plainTitle('legacy/details.title_show_or_hide'),
            downloadAllowed: $downloadAllowed,
            saveAs: (string) $row['save_as'],
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function buildTitle(array $row): TorrentTitleLine
    {
        $ignoreGlobal = (bool) ($row['__ignore_global_sp_state'] ?? false);
        $promotion = Promotion::badgeWithContext(
            (int) $row['sp_state'], 'word', false, '', 0, '', $ignoreGlobal
        );
        $sub = Promotion::badgeWithContext(
            (int) $row['sp_state'], '', true, $row['added'] ?? null,
            (int) ($row['promotion_time_type'] ?? 0), $row['promotion_until'] ?? null,
            $ignoreGlobal
        );
        if ($promotion !== null && $sub?->timeout !== null) {
            $promotion = new PromotionBadge(
                mode: $promotion->mode,
                cssClass: $promotion->cssClass,
                iconClass: $promotion->iconClass,
                alt: $promotion->alt,
                text: $promotion->text,
                timeout: $sub->timeout,
                subColor: $sub->subColor,
                domttHtml: $promotion->domttHtml,
            );
        }

        return new TorrentTitleLine(
            name: (string) $row['name'],
            banned: ($row['banned'] ?? 0) == 1,
            badges: new TorrentBadgeSet(
                paid: isset($row['price']) && $row['price'] > 0,
                promotion: $promotion,
                hitAndRun: TorrentAccess::requiresHrIcon($row, (int) ($row['search_box_id'] ?? 0)),
                approval: $this->moderationRepository->shouldShowApprovalStatusIcon($row['approval_status'] ?? null)
                    ? new ApprovalBadge(
                        title: (string) Locale::trans("torrent.approval.status_text.{$row['approval_status']}", [], null),
                        icon: Torrent::approvalStatusIcon((int) $row['approval_status']),
                    )
                    : null,
            ),
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function buildOwner(array $row, bool $isOwner): OwnerAttribution
    {
        if (($row['anonymous'] ?? 0) == 1) {
            $reveal = Permission::can(PermissionEnum::VIEW_ANONYMOUS) || $isOwner;

            return new OwnerAttribution(
                anonymous: true,
                username: $reveal ? UserDisplay::username((int) ($row['owner'] ?? 0), false, true, true, false, false, true) : null,
                showUsername: $reveal,
            );
        }

        return new OwnerAttribution(
            anonymous: false,
            username: isset($row['owner'])
                ? UserDisplay::username((int) $row['owner'], false, true, true, false, false, true)
                : null,
            showUsername: true,
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return list<TaxonomyEntry>
     */
    private function buildTaxonomy(array $row): array
    {
        $entries = [];
        foreach ($this->searchBoxSchemaBuilder->listTaxonomyInfo((int) ($row['search_box_id'] ?? 0), $row) as $item) {
            $entries[] = new TaxonomyEntry((string) ($item['label'] ?? ''), (string) ($item['value'] ?? ''));
        }

        return $entries;
    }

    /**
     * @param  array<int|string, mixed>  $row
     * @return list<TorrentAction>
     */
    private function buildActions(int $id, array $row, bool $hasBuy, bool $owned, bool $downloadAllowed, string $returnto): array
    {
        $actions = [];

        if ($downloadAllowed) {
            if ($row['price'] > 0) {
                $downloadLabel = $hasBuy
                    ? (string) __('legacy/details.text_download_bought_torrent')
                    : sprintf((string) __('legacy/details.text_download_paid_torrent'), number_format((float) $row['price']));
            } else {
                $downloadLabel = (string) __('legacy/details.text_download_torrent');
            }
            $actions[] = new TorrentAction(
                url: "/download?id={$id}",
                title: self::plainTitle('legacy/details.title_download_torrent'),
                iconClass: 'dt_download',
                iconAlt: 'download',
                label: $downloadLabel,
            );
        }

        if ($owned) {
            $editUrl = "/edit?id={$id}";
            if ($returnto !== '') {
                $editUrl .= '&returnto='.rawurlencode($returnto);
            }
            $actions[] = new TorrentAction(
                url: $editUrl,
                title: self::plainTitle('legacy/details.title_edit_torrent'),
                iconClass: 'dt_edit',
                iconAlt: 'edit',
                label: Permission::can(PermissionEnum::TORRENT_MANAGE)
                    ? (string) __('legacy/details.text_edit_and_delete_torrent')
                    : (string) __('legacy/details.text_edit_torrent'),
            );
        }

        if (Permission::can(PermissionEnum::ASK_RESEED) && (int) $row['seeders'] === 0) {
            $actions[] = new TorrentAction(
                url: '/web/torrents/reseed',
                title: self::plainTitle('legacy/details.title_ask_for_reseed'),
                iconClass: 'dt_reseed',
                iconAlt: 'reseed',
                label: (string) __('legacy/details.text_ask_for_reseed'),
                isPost: true,
                postFields: ['reseedid' => $id],
            );
        }

        if (
            Permission::can(PermissionEnum::TORRENT_APPROVAL)
            && (SiteConfig::current()->torrent->approvalStatusIconEnabled() || ! SiteConfig::current()->torrent->approvalStatusNoneVisible())
        ) {
            $actions[] = new TorrentAction(
                url: '#',
                title: '',
                iconClass: '',
                iconAlt: '',
                label: (string) __('legacy/details.action_approval'),
                spanClass: 'small approval',
                spanId: 'approval',
                dataTorrentId: $id,
                iconHtml: SafeHtml::fromTrustedHtml(trim(view('torrents._icon-approval')->render())),
            );
            $approvalTitle = Locale::trans('torrent.approval.modal_title', [], null);
            AssetAppender::js(sprintf(<<<'JS'
document.getElementById('approval').addEventListener("click", function () {
    var torrentId = this.getAttribute('data-torrent_id')
    layer.open({
        type: 2,
        title: %s,
        area: ['60%%', '600px'],
        content: '/web/torrent-approval-page?torrent_id=' + torrentId,
    })
})
JS, \json_encode($approvalTitle)), 'footer', false);
        }

        $actions[] = new TorrentAction(
            url: "/web/report?torrent={$id}",
            title: self::plainTitle('legacy/details.title_report_torrent'),
            iconClass: 'dt_report',
            iconAlt: 'report',
            label: (string) __('legacy/details.text_report_torrent'),
        );

        return $actions;
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function buildInfoRow(int $id, array $row): TorrentInfoRow
    {
        return new TorrentInfoRow(
            torrentId: $id,
            numFiles: TorrentType::tryFrom((int) ($row['type'] ?? 0)) === TorrentType::MULTI ? (int) $row['numfiles'] : null,
            infoHash: bin2hex(Strings::padHash($row['info_hash'])),
            showStructure: Permission::can(PermissionEnum::TORRENT_STRUCTURE),
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function buildHotMeter(int $id, array $row): HotMeterRow
    {
        $snatchesPre = (string) __('legacy/details.text_view_snatches_pre');
        $snatchesPost = (string) __('legacy/details.text_view_snatches_post');

        return new HotMeterRow(
            views: $row['views'],
            hits: $row['hits'],
            timesCompleted: $row['times_completed'],
            torrentId: $id,
            lastSeeder: SafeHtml::fromTrustedHtml((string) Time::format((string) $row['last_action'])),
            lastSeederLabel: self::plainTitle('legacy/details.row_last_seeder'),
            snatchesPre: $snatchesPre,
            snatchesPost: $snatchesPost,
        );
    }

    /**
     * @param  array<int|string, mixed>  $row
     */
    private function buildDenyBanner(array $row, ?TorrentOperationLog $denyLog): ?DenyBanner
    {
        if (($row['approval_status'] ?? null) != TorrentApprovalStatus::DENY->value || $denyLog === null) {
            return null;
        }

        return new DenyBanner(
            message: SafeHtml::fromTrustedHtml(
                (string) Locale::trans('torrent.approval.deny_comment_show', ['reason' => $denyLog->comment], null)
            ),
        );
    }

    /**
     * Legacy lang values may carry `&nbsp;`-style entities; decode to
     * plain text so attribute escaping renders them correctly.
     */
    private static function plainTitle(string $key): string
    {
        return html_entity_decode((string) __($key), ENT_QUOTES | ENT_HTML401, 'UTF-8');
    }
}
