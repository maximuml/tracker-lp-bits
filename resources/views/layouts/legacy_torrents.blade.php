@php
\App\Support\Html::stdhead($__env->yieldContent('title'));
@endphp
<div id="main-content" tabindex="-1">
@yield('content')
</div>
@php
\App\Support\Html::stdfoot();
@endphp
