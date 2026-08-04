@php
stdhead($title ?? '');
begin_main_frame();
@endphp

@yield('content')

@php
end_main_frame();
stdfoot();
@endphp
