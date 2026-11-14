<?php

declare(strict_types=1);

namespace App\ViewModels;

use Illuminate\Support\Collection;

/**
 * ViewModel for the invite page (invite/index.blade.php).
 *
 * Three render modes share one template: the new-invite form
 * (`type === 'new'`) and the tabbed lists (`menuSelected` =
 * invitee | sent | tmp). Mode-specific props keep their defaults
 * outside their mode.
 */
final class InvitePageViewModel extends ViewModel
{
    /**
     * @param  array<string, mixed>  $CURUSER  legacy current-user row (view reads `['id']`)
     * @param  list<array{id: string, label: string, url: string}>  $inviteTabs
     * @param  array<string, mixed>  $inv  invitee user row for the new form
     * @param  Collection<int, mixed>|array<int, mixed>  $temporaryInvites
     * @param  list<array{value: string, text: string}>  $inviteOptions
     * @param  list<array{value: string, text: string, selected: bool}>  $inviteeEnabledOptions
     * @param  list<array{value: string, text: string, selected: bool}>  $inviteeStatusOptions
     * @param  list<array<string, mixed>>  $inviteeRows
     * @param  array{page?: int, pages?: int, href?: string, prevLabel?: string, nextLabel?: string, zeroBased?: bool}  $inviteePager
     * @param  list<array<string, mixed>>  $sentTmpRows
     * @param  array{page?: int, pages?: int, href?: string, prevLabel?: string, nextLabel?: string, zeroBased?: bool}  $sentTmpPager
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $menuSelected,
        public readonly string $inviteTitle,
        public readonly array $inviteTabs,
        public readonly array $CURUSER,
        public readonly string $SITENAME,
        public readonly string $__server_REQUEST_URI,
        public readonly string $sent,
        public readonly string $sendBtnText = '',
        public readonly string $sendBtnDisabled = '',
        public readonly array $inv = [],
        public readonly string $_s = '',
        public readonly Collection|array $temporaryInvites = [],
        public readonly bool $showPreUsername = false,
        public readonly string $preUsernameLabel = '',
        public readonly string $preUsernameHelp = '',
        public readonly array $inviteOptions = [],
        public readonly string $invitation_body = '',
        public readonly int $inviteeCount = 0,
        public readonly array $inviteeRows = [],
        public readonly array $inviteePager = [],
        public readonly array $inviteeEnabledOptions = [],
        public readonly array $inviteeStatusOptions = [],
        public readonly float $haremAdditionFactor = 0.0,
        public readonly int $pendingCount = 0,
        public readonly bool $canConfirm = false,
        public readonly int $inviteeColSpan = 12,
        public readonly string $textSelectOnePlease = '',
        public readonly string $resetText = '',
        public readonly string $submitText = '',
        public readonly int $sentTmpCount = 0,
        public readonly array $sentTmpRows = [],
        public readonly array $sentTmpPager = [],
    ) {}

    /**
     * Build from the controller's raw page-data array, ignoring keys the
     * template does not read.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromPageData(array $data): self
    {
        return new self(...array_intersect_key($data, array_flip([
            'id', 'type', 'menuSelected', 'inviteTitle', 'inviteTabs', 'CURUSER', 'SITENAME',
            '__server_REQUEST_URI', 'sent', 'sendBtnText', 'sendBtnDisabled', 'inv', '_s',
            'temporaryInvites', 'showPreUsername', 'preUsernameLabel', 'preUsernameHelp',
            'inviteOptions', 'invitation_body', 'inviteeCount', 'inviteeRows', 'inviteePager',
            'inviteeEnabledOptions', 'inviteeStatusOptions', 'haremAdditionFactor', 'pendingCount',
            'canConfirm', 'inviteeColSpan', 'textSelectOnePlease', 'resetText', 'submitText',
            'sentTmpCount', 'sentTmpRows', 'sentTmpPager',
        ])));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
