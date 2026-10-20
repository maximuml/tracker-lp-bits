@include('layouts.partials.head-assets')
@include('layouts.partials.header')
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
@include('layouts.partials.footer')
