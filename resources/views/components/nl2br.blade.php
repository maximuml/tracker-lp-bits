@props(['text' => ''])
@foreach (explode("\n", trim((string) $text)) as $line){{ $line }}@if (! $loop->last)<br>@endif
@endforeach
