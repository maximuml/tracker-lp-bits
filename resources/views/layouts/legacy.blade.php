{{ app(\App\Support\PageRenderer::class)->headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
<table class="main nx-mainouter" style="width:{{ CONTENT_WIDTH }}px"><tr><td class="embedded" >

@yield('content')

</td></tr></table>
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
