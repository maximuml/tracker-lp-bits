{{ app(\App\Support\PageRenderer::class)->headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
<table class="main" width="{{ CONTENT_WIDTH }}" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded" >

@yield('content')

</td></tr></table>
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
