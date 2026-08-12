@php
\App\Support\Html::stdhead($title ?? $__env->yieldContent('title'));
\App\Support\Frame::mainFrameOpen();
@endphp

@yield('content')

@php
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
@endphp
