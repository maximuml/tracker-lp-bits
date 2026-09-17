@props(['caption', 'center' => true])
{{ \App\Support\Frame::open((string) $caption, (bool) $center, 10, '100%', 'left') }}
{{ $slot }}
{{ \App\Support\Frame::close() }}
