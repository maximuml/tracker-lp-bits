@php
\App\Support\Html::stdhead(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
);
\App\Support\Frame::mainFrameOpen();
@endphp

@yield('content')

@php
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
@endphp
