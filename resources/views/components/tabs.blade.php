@props(['tabs' => [], 'active' => null, 'label' => 'Sections'])
<nav {{ $attributes->merge(['class' => 'nx-tabs']) }} aria-label="{{ $label }}">
    <ul class="nx-tabs__list">
        @foreach ($tabs as $tab)
            <li class="nx-tabs__item">
                <a
                    class="nx-tabs__link{{ ($tab['id'] ?? null) === $active ? ' nx-tabs__link--active' : '' }}"
                    href="{{ $tab['url'] ?? '#' }}"
                    @if (($tab['id'] ?? null) === $active) aria-current="page" @endif
                >{{ $tab['label'] ?? '' }}</a>
            </li>
        @endforeach
    </ul>
</nav>
