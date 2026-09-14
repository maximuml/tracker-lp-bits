@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\PageLayout::headerHtml($__env->yieldContent('title'))))
<div id="main-content" tabindex="-1">
@yield('content')
</div>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\PageLayout::footerHtml()))
