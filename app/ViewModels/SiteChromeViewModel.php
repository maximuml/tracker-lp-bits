<?php

declare(strict_types=1);

namespace App\ViewModels;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Support\Html\SafeHtml;
use App\Support\PageLayoutContext;
use App\ViewModels\Chrome\ChromeAlerts;
use App\ViewModels\Chrome\ChromeFooter;
use App\ViewModels\Chrome\ChromeHead;
use App\ViewModels\Chrome\ChromeNav;
use App\ViewModels\Chrome\ChromeRepositories;
use App\ViewModels\Chrome\ChromeSearch;
use App\ViewModels\Chrome\ChromeUserBar;

/**
 * Data for the shared page chrome (`layouts.partials.*`, ADR 0018).
 *
 * Variant A (ADR 0014) gave `layouts/modern` a semantic HTML5 shell; ADR 0018
 * reuses the same partials for legacy pages rendered through
 * `PageRenderer::headerHtml()`/`footerHtml()`. This view model composes the
 * per-section view models — `ChromeUserBar`, `ChromeHead`, `ChromeSearch`,
 * `ChromeFooter` — with the site identity fields, nav items and alert
 * banners, as plain data; markup lives in the partials. `variant` selects
 * the chrome flavour (legacy keeps its theme stylesheets and head-loaded
 * scripts until page bodies migrate in stage 3).
 */
final class SiteChromeViewModel
{
    /**
     * @param  array<string, mixed>|null  $user
     * @param  list<array{key: string, href: string, label: string, selected: bool, attrs: string}>  $navItems
     * @param  list<array{url: string, text: SafeHtml, color: string}>  $alerts
     */
    private function __construct(
        public readonly string $siteName,
        public readonly string $slogan,
        public readonly string $logoMain,
        public readonly string $baseUrl,
        public readonly string $variant,
        public readonly ?array $user,
        public readonly array $navItems,
        public readonly array $alerts,
        public readonly bool $offlineMsg,
        public readonly SafeHtml $offlineMsgHtml,
        public readonly bool $enableDonation,
        public readonly ChromeUserBar $userBar,
        public readonly ChromeSearch $search,
        public readonly ChromeHead $head,
        public readonly ChromeFooter $footer,
    ) {}

    public static function load(
        string $title,
        PageLayoutRepositoryInterface $repo,
        ChromeRepositories $chrome,
        ?PageLayoutContext $context = null,
        string $variant = 'modern',
        bool $msgalert = true,
        bool $skipUserData = false,
    ): self {
        $context ??= PageLayoutContext::fromSupportContext();
        $user = $context->user;
        $cspNonce = (string) (request()->attributes->get('csp_nonce', ''));

        $userBar = ChromeUserBar::load($context, $repo, $chrome, $skipUserData);
        $navItems = ChromeNav::items($context, $chrome);

        $alerts = [];
        if ($user !== null && ! empty($user['id']) && ! $skipUserData) {
            $alerts = ChromeAlerts::load($context, (int) $user['id'], $userBar->unreadCount, $repo, $msgalert, $chrome);
        }

        return new self(
            siteName: $context->siteName,
            slogan: $context->slogan,
            logoMain: $context->logoMain,
            baseUrl: $context->baseUrl,
            variant: $variant,
            user: $user,
            navItems: $navItems,
            alerts: $alerts,
            offlineMsg: $context->offlineMsg,
            offlineMsgHtml: SafeHtml::fromTrustedHtml(view('components.offline-warning')->render()),
            enableDonation: $context->enableDonation === 'yes',
            userBar: $userBar,
            search: ChromeSearch::load($context),
            head: ChromeHead::load($context, $title, $variant, $cspNonce, $chrome),
            footer: ChromeFooter::load($context, $variant, $cspNonce),
        );
    }
}
