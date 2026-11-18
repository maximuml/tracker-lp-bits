@props(['selects'])
@foreach ($selects as $select){{ $select }}@if (! $loop->last)&nbsp;&nbsp;@endif@endforeach
