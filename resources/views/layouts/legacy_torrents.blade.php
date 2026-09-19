{{ \App\Support\PageLayout::headerHtml($__env->yieldContent('title')) }}
@yield('content')
{{ \App\Support\PageLayout::footerHtml() }}
