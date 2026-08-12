@php
\App\Support\Html::stdhead($__env->yieldContent('title'));
@endphp
@yield('content')
@php
\App\Support\Html::stdfoot();
@endphp
