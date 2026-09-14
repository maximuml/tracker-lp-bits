@php
\App\Support\PageLayout::setContext(\App\Support\PageLayoutContext::fromSupportContext());
\App\Support\PageLayout::header($__env->yieldContent('title'));
@endphp
<div id="main-content" tabindex="-1">
@yield('content')
</div>
@php
\App\Support\PageLayout::footer();
@endphp
