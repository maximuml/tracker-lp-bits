{{ app(\App\Support\PageRenderer::class)->headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
<table class="main nx-mx-auto" width="{{ CONTENT_WIDTH }}" cellspacing="0" cellpadding="0"><tr><td class="embedded" >

@yield('content')

</td></tr></table>
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
