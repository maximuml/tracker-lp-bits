<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\ViewModels\Chrome\ChromeRepositories;
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
 * `boxed` is the default — the bulk of the site renders inside the
 * `nx-pagebox` wrapper; modern and auth pages opt in explicitly. Views
 * that already carry
 * a `chrome` variable (the procedural `PageRenderer` partials) are left
 * untouched.
 */
final class SiteChromeComposer
{
    public function __construct(
        private readonly PageLayoutRepositoryInterface $layouts,
        private readonly ChromeRepositories $chromeRepositories,
    ) {}

    public function compose(View $view): void
    {
        if (array_key_exists('chrome', $view->getData())) {
            return;
        }

        $data = $view->getData();
        $variant = (string) ($data['chromeVariant'] ?? 'boxed');
        $view->with('chromeVariant', $variant);
        $view->with('shell', (string) ($data['shell'] ?? match ($variant) {
            'boxed' => 'boxed',
            'auth' => 'auth',
            default => 'bare',
        }));

        // Two-argument @section('title', $val) stores e($val) — decode so
        // the single {{ }} escape in <title> does not double-escape.
        $title = html_entity_decode(
            trim($view->getFactory()->yieldContent('title')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
        $view->with('chrome', SiteChromeViewModel::load(
            $title,
            $this->layouts,
            $this->chromeRepositories,
            variant: $variant,
            msgalert: (bool) ($data['stdheadMsgalert'] ?? true),
        ));
        $view->with('locale', str_replace('_', '-', App::getLocale()));
    }
}
