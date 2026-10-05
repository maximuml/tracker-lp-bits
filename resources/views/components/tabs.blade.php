@props(['tabs' => [], 'active' => null, 'label' => 'Sections'])
<nav {{ $attributes->merge(['class' => '']) }} aria-label="{{ $label }}">
    <ul class="m-0 flex list-none flex-wrap gap-0.5 border-b border-nxm-border p-0">
        @foreach ($tabs as $tab)
            <li>
                <a
                    class="inline-block border border-b-0 px-3 py-1.5 no-underline focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-nxm-accent {{ ($tab['id'] ?? null) === $active ? 'border-nxm-border bg-nxm-surface font-bold text-nxm-text' : 'border-transparent text-nxm-accent-text' }}"
                    href="{{ $tab['url'] ?? '#' }}"
                    @if (($tab['id'] ?? null) === $active) aria-current="page" @endif
                >{{ $tab['label'] ?? '' }}</a>
            </li>
        @endforeach
    </ul>
</nav>
