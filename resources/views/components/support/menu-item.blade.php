@props(['href', 'label', 'selected' => false, 'subMenu' => false])
@if($selected && $subMenu)<li class="selected"><a href="{{ $href }}" rel='sub-menu'>{{ $label }}</a></li>@elseif($selected)<li class="selected"><a href="{{ $href }}">{{ $label }}</a></li>@elseif($subMenu)<li><a href="{{ $href }}" rel='sub-menu'>{{ $label }}</a></li>@else<li><a href="{{ $href }}">{{ $label }}</a></li>@endif
