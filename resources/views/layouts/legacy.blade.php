{{ \App\Support\PageLayout::headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
{{ \App\Support\Frame::mainOpen('', false, 100, (int) \constant('CONTENT_WIDTH')) }}

<div id="main-content" tabindex="-1">
@yield('content')
</div>

{{ \App\Support\Frame::close() }}
{{ \App\Support\PageLayout::footerHtml() }}
