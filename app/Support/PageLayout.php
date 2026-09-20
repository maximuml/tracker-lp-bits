<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\Support\Html\SafeHtml;
use App\ViewModels\SiteChromeViewModel;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;

class PageLayout
{
    /**
     * @param  string  $title
     * @param  bool  $msgalert
     * @param  string  $script
     * @param  string  $place
     * @return void
     */
    private static ?PageLayoutContext $context = null;

    public static function setContext(PageLayoutContext $context): void
    {
        self::$context = $context;
    }

    public static function getContext(): ?PageLayoutContext
    {
        return self::$context;
    }

    public static function resetState(): void
    {
        self::$context = null;
    }

    public static function header(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        echo self::renderHeader($context, $title, $msgalert, $script, $place);
    }

    /**
     * Rendered variant of stdhead(): sets the context from SupportContext
     * and returns the header markup instead of echoing it.
     */
    public static function headerHtml(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): SafeHtml
    {
        $context = PageLayoutContext::fromSupportContext();
        self::setContext($context);

        return SafeHtml::fromTrustedHtml(self::renderHeader($context, $title, $msgalert, $script, $place));
    }

    /**
     * Rendered variant of stdfoot(): returns the footer markup.
     */
    public static function footerHtml(): SafeHtml
    {
        return SafeHtml::fromTrustedHtml(self::renderFooter());
    }

    private static function renderHeader(PageLayoutContext $context, string $title, bool $msgalert, string $script, string $place): string
    {
        $context->cache?->setLanguage($context->langDir);
        if ($context->siteOnline == 'no') {
            if ($context->userClass() < $context->adminClass) {
                throw new HttpResponseException(new Response((string) (__('legacy/functions.std_site_down_for_maintenance')), 503));
            } else {
                $context->offlineMsg = true;
            }
        }

        $chrome = SiteChromeViewModel::load($title, app(PageLayoutRepositoryInterface::class), $context, 'legacy', $msgalert);

        return view('layouts.partials.head-assets', ['chrome' => $chrome])->render()
            .view('layouts.partials.header', ['chrome' => $chrome])->render();
    }

    public static function footer(): void
    {
        echo self::renderFooter();
    }

    private static function renderFooter(): string
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $chrome = SiteChromeViewModel::load('', app(PageLayoutRepositoryInterface::class), $context, 'legacy', false, true);

        return view('layouts.partials.footer', ['chrome' => $chrome])->render();
    }
}
