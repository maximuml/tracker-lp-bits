@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\PageLayout::headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
)))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::mainOpen('', false, 100, (int) \constant('CONTENT_WIDTH'))))

<div id="main-content" tabindex="-1">
@yield('content')
</div>

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Frame::CLOSE))
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\PageLayout::footerHtml()))
