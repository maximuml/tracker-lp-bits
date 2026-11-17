@props(['class'])
@php
    $slug = \App\Support\UserClass::badgeSlug($class);
    $badgeName = \App\Support\UserClass::name($class, false, false, true);
@endphp
<span class="nx-class-badge nx-class-badge--{{ $slug }}">{{ $badgeName }}</span>
