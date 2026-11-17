@props(['class'])
<span class="nx-class-badge nx-class-badge--{{ \App\Support\UserClass::badgeSlug($class) }}">{{ \App\Support\UserClass::name($class, false, false, true) }}</span>
