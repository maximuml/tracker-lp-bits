@props(['iconClass', 'name'])
@if($iconClass !== '')<img class="{{ $iconClass }}" src="pic/cattrans.gif" alt="{{ $name }}" title="{{ $name }}" />@else<img src="pic/cattrans.gif" alt="{{ $name }}" title="{{ $name }}" />@endif
