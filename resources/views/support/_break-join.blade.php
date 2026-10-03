@props(['parts'])
@foreach($parts as $part){{ $part }}@if(!$loop->last)<br /> @endif@endforeach
