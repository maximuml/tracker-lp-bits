<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Contracts\Repositories\PageLayoutRepositoryInterface;
use App\ViewModels\SiteChromeViewModel;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * Injects the shared page chrome into `layouts.modern` and the chrome
 * partials (ADR 0018).
 *
 * Class-based composer so the repository arrives via container injection
 * instead of service location inside the view model. The page title is read
 * from the already-rendered child's `title` section. Views that already
 * carry a `chrome` variable — `PageLayout` passes the legacy variant
 * explicitly — are left untouched.
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

        $title = trim($view->getFactory()->yieldContent('title'));
        $variant = $view->name() === 'layouts.auth' ? 'auth' : 'modern';
        $view->with('chrome', SiteChromeViewModel::load($title, $this->layouts, variant: $variant));
        $view->with('locale', str_replace('_', '-', App::getLocale()));
    }
}
