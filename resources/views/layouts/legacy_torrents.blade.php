{{ \App\Support\PageLayout::headerHtml($__env->yieldContent('title')) }}
<div id="main-content" tabindex="-1">
@yield('content')
</div>
{{ \App\Support\PageLayout::footerHtml() }}
