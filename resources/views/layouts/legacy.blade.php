{{ \App\Support\PageLayout::headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
{{ \App\Support\Frame::mainOpen('', false, 100, (int) \constant('CONTENT_WIDTH')) }}

@yield('content')

{{ \App\Support\Frame::close() }}
{{ \App\Support\PageLayout::footerHtml() }}
