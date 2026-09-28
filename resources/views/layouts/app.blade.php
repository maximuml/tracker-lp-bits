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
<div class="nx-pagebox nx-main">

@yield('content')

</div>
@else
@yield('content')
@endif
@if ($chromeVariant === 'legacy')
{{ app(\App\Support\PageRenderer::class)->footerHtml() }}
@else
@include('layouts.partials.footer')
@endif
