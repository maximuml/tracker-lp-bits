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

        self::renderHeader($context, $title, $msgalert, $script, $place);
    }

    /**
     * Buffered variant of stdhead(): sets the context from SupportContext
     * and returns the header markup instead of echoing it.
     */
    public static function headerHtml(string $title = '', bool $msgalert = true, string $script = '', string $place = ''): SafeHtml
    {
        self::setContext(PageLayoutContext::fromSupportContext());

        return SafeHtml::fromTrustedHtml(self::buffered(fn () => self::header($title, $msgalert, $script, $place)));
    }

    /**
     * Buffered variant of stdfoot(): returns the footer markup.
     */
    public static function footerHtml(): SafeHtml
    {
        return SafeHtml::fromTrustedHtml(self::buffered(fn () => self::footer()));
    }

    /**
     * @param  callable(): void  $fn
     */
    private static function buffered(callable $fn): string
    {
        ob_start();
        try {
            $fn();

            return (string) ob_get_clean();
        } catch (\Throwable $e) {
            ob_end_clean();

            throw $e;
        }
    }

    private static function renderHeader(PageLayoutContext $context, string $title, bool $msgalert, string $script, string $place): void
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

        echo view('layouts.partials.head-assets', ['chrome' => $chrome])->render();
        echo view('layouts.partials.header', ['chrome' => $chrome])->render();
    }

    public static function footer(): void
    {
        $context = self::getContext();
        if ($context === null) {
            throw new \RuntimeException('PageLayout context not set');
        }

        $chrome = SiteChromeViewModel::load('', app(PageLayoutRepositoryInterface::class), $context, 'legacy', false, true);

        echo view('layouts.partials.footer', ['chrome' => $chrome])->render();
    }
}
