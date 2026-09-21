<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\ViewModels\SiteChromeViewModel;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Injects the shared page chrome into `layouts.app` and the chrome
 * partials (ADR 0018).
 *
 * Class-based composer so the repository arrives via container injection
 * instead of service location inside the view model. The page title is read
 * from the already-rendered child's `title` section. The chrome variant is
 * read from the `chromeVariant` view data (`@extends('layouts.app', [...])`);
 * `legacy` pages render their chrome through `PageRenderer` and views that
 * already carry a `chrome` variable are left untouched.
 */
final class SiteChromeComposer
{
    public function __construct(
        private readonly PageLayoutRepositoryInterface $layouts,
    ) {}

    public function compose(View $view): void
    {
        if (array_key_exists('chrome', $view->getData())) {
            return;
        }

        $data = $view->getData();
        $variant = (string) ($data['chromeVariant'] ?? 'modern');
        $view->with('chromeVariant', $variant);
        $view->with('shell', (string) ($data['shell'] ?? match ($variant) {
            'legacy' => 'boxed',
            'auth' => 'auth',
            default => 'bare',
        }));

        if ($variant === 'legacy') {
            return;
        }

        $title = trim($view->getFactory()->yieldContent('title'));
        $view->with('chrome', SiteChromeViewModel::load($title, $this->layouts, variant: $variant));
        $view->with('locale', str_replace('_', '-', App::getLocale()));
    }
}
