@props(['caption'])
{{ \App\Support\Frame::open((string) $caption, true, 10, '100%', 'left') }}
{{ \App\Support\Frame::tableOpen(false, 5) }}
{{ $slot }}
{{ \App\Support\Frame::tableClose() }}
{{ \App\Support\Frame::close() }}
