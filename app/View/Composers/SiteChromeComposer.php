<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\ViewModels\SiteChromeViewModel;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Injects the modern page chrome into `layouts.modern` (Variant A, ADR 0014).
 *
 * Class-based composer so the repository arrives via container injection
 * instead of service location inside the view model. The page title is read
 * from the already-rendered child's `title` section.
 */
final class SiteChromeComposer
{
    public function __construct(
        private readonly PageLayoutRepositoryInterface $layouts,
    ) {}

    public function compose(View $view): void
    {
        $title = trim($view->getFactory()->yieldContent('title'));
        $view->with('chrome', SiteChromeViewModel::load($title, $this->layouts));
        $view->with('locale', str_replace('_', '-', App::getLocale()));
    }
}
