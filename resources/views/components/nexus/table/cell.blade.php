@props([
    'header' => false,
])

@php
$classes = $header
    ? 'px-4 py-2 text-left font-semibold'
    : 'px-4 py-2';
@endphp

@if ($header)
    <th {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </th>
@else
    <td {{ $attributes->merge(['class' => $classes]) }}>
        {{ $slot }}
    </td>
@endif
