@props(['icon', 'second' => null])
@if ($icon === null)-@else
@if ($icon->href !== null)<a href="{{ $icon->href }}">@endif<img @if ($icon->iconClass !== '')class="{{ $icon->iconClass }}" @endif src="pic/cattrans.gif" alt="{{ $icon->name }}" title="{{ $icon->name }}" />@if ($icon->href !== null)</a>@endif
@if ($second !== null)<img @if ($second->iconClass !== '')class="{{ $second->iconClass }}" @endif src="pic/cattrans.gif" alt="{{ $second->name }}" title="{{ $second->name }}" />@endif
@endif
