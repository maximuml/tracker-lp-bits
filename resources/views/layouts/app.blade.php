@if ($chromeVariant === 'legacy')
{{ app(\App\Support\PageRenderer::class)->headerHtml(
    $title ?? $__env->yieldContent('title'),
    $stdheadMsgalert ?? true,
    $stdheadScript ?? '',
    $stdheadPlace ?? ''
) }}
@else
@include('layouts.partials.head-assets')
@include('layouts.partials.header')
@endif
@if ($shell === 'auth')
<div class="nx-auth">
@yield('content')
</div>
@elseif ($shell === 'boxed')
<table class="main nx-mx-auto" width="{{ CONTENT_WIDTH }}" cellspacing="0" cellpadding="0"><tr><td class="embedded" >

@yield('content')

</td></tr></table>
@else
@yield('content')
@endif
@if ($chromeVariant === 'legacy')
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
@else
@include('layouts.partials.footer')
@endif
