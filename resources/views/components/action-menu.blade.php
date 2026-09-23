@props(['label', 'items' => [], 'align' => 'right'])
<details {{ $attributes->merge(['class' => 'nx-action-menu nx-action-menu--'.$align]) }}>
    <summary class="nx-action-menu__toggle nx-btn" aria-haspopup="menu">
        {{ $label }}<span class="nx-action-menu__caret" aria-hidden="true">&#9662;</span>
    </summary>
    <ul class="nx-action-menu__list" role="menu">
        @foreach ($items as $item)
            <li role="none">
                <a
                    class="nx-action-menu__item{{ ($item['danger'] ?? false) ? ' nx-action-menu__item--danger' : '' }}"
                    role="menuitem"
                    href="{{ $item['href'] ?? '#' }}"
                >{{ $item['label'] ?? '' }}</a>
            </li>
        @endforeach
        {{ $slot }}
    </ul>
</details>
