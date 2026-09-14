@php
\App\Support\PageLayout::setContext(\App\Support\PageLayoutContext::fromSupportContext());
\App\Support\PageLayout::header(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
);
\App\Support\Frame::mainFrameOpen();
@endphp

<div id="main-content" tabindex="-1">
@yield('content')
</div>

@php
\App\Support\Frame::mainFrameClose();
\App\Support\PageLayout::footer();
@endphp
