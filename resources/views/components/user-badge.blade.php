@props(['username', 'level' => null, 'href' => null])
@if ($href !== null)
    <a {{ $attributes->merge(['class' => 'nx-user-badge'.($level !== null ? ' nx-user-badge--'.$level : '')]) }} href="{{ $href }}">{{ $username }}</a>
@else
    <span {{ $attributes->merge(['class' => 'nx-user-badge'.($level !== null ? ' nx-user-badge--'.$level : '')]) }}>{{ $username }}</span>
@endif
