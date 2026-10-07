<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Support\Html\SafeHtml;
use App\ViewModels\Chrome\ChromeRepositories;
use App\ViewModels\SiteChromeViewModel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

/**
 * Request-scoped legacy page chrome renderer (replaces the static
 * `PageLayout` facade). The instance is a container singleton flushed by
 * `ResetNexus` between requests/jobs — same lifecycle the static state
 * had, without the statics.
 */
final class PageRenderer
{
    private ?PageLayoutContext $context = null;

    public function __construct(
        private readonly PageLayoutRepositoryInterface $layoutRepository,
        private readonly ChromeRepositories $chromeRepositories,
    ) {}

    public function setContext(PageLayoutContext $context): void
    {
        $this->context = $context;
    }

    public function hasContext(): bool
    {
        return $this->context !== null;
    }

    public function reset(): void
    {
        $this->context = null;
    }

    /**
     * Rendered variant of stdhead(): sets the context from SupportContext
     * and returns the header markup instead of echoing it.
     */
    public function headerHtml(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): SafeHtml
    {
        $context = PageLayoutContext::fromSupportContext();
        $this->setContext($context);

        return SafeHtml::fromTrustedHtml($this->renderHeader($context, $title, $msgalert, $script, $place));
    }

    /**
     * Rendered variant of stdfoot(): returns the footer markup.
     */
    public function footerHtml(): SafeHtml
    {
        return SafeHtml::fromTrustedHtml($this->renderFooter());
    }

    public function footer(): void
    {
        echo $this->renderFooter();
    }

    private function renderHeader(PageLayoutContext $context, string $title, bool $msgalert, string $script, string $place): string
    {
        $context->cache?->setLanguage($context->langDir);
        if ($context->siteOnline == 'no') {
            if ($context->userClass() < $context->adminClass) {
                throw new HttpResponseException(new Response((string) (__('functions.std_site_down_for_maintenance')), 503));
            } else {
                $context->offlineMsg = true;
            }
        }

        $chrome = SiteChromeViewModel::load($title, $this->layoutRepository, $this->chromeRepositories, $context, 'legacy', $msgalert);

        return view('layouts.partials.head-assets', ['chrome' => $chrome])->render()
            .view('layouts.partials.header', ['chrome' => $chrome])->render();
    }

    private function renderFooter(): string
    {
        $context = $this->context;
        if ($context === null) {
            throw new \RuntimeException('PageRenderer context not set');
        }

        $chrome = SiteChromeViewModel::load('', $this->layoutRepository, $this->chromeRepositories, $context, 'legacy', false, true);

        return view('layouts.partials.footer', ['chrome' => $chrome])->render();
    }
}
