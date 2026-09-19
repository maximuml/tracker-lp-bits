@props(['progress'])
@if ($progress !== null)<progress class="nx-progress nx-progress--{{ $progress->status }}" max="100" value="{{ $progress->percent }}" title="{{ $progress->status }} {{ $progress->percent }}%">{{ $progress->percent }}%</progress>@endif
