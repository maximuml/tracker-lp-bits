@props(['type'])
<span {{ $attributes->merge(['class' => 'nx-tbadge nx-tbadge--'.$type]) }}>{{ $slot }}</span>
