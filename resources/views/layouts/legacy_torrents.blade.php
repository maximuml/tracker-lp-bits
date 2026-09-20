{{ app(\App\Support\PageRenderer::class)->headerHtml($__env->yieldContent('title')) }}
@yield('content')
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
