@props(['label', 'items' => [], 'align' => 'right'])
<details {{ $attributes->merge(['class' => 'relative inline-block']) }}>
    <summary class="inline-block cursor-pointer select-none rounded-[3px] border border-nxm-border bg-nxm-surface-alt px-3.5 py-1 text-xs text-nxm-text [list-style:none] [&::-webkit-details-marker]:hidden" aria-haspopup="menu">
        {{ $label }}<span class="ml-1.5" aria-hidden="true">&#9662;</span>
    </summary>
    <ul class="absolute top-full z-[1000] mt-0.5 min-w-40 list-none rounded border border-nxm-border bg-nxm-surface py-1 shadow-[0_4px_16px_rgba(0,0,0,.2)] {{ $align === 'right' ? 'right-0' : 'left-0' }}" role="menu">
        @foreach ($items as $item)
            <li role="none">
                <a
                    class="block whitespace-nowrap px-3 py-1.5 no-underline hover:bg-nxm-accent hover:text-nxm-on-accent focus-visible:bg-nxm-accent focus-visible:text-nxm-on-accent focus-visible:outline-none {{ ($item['danger'] ?? false) ? 'text-nxm-danger hover:bg-nxm-danger focus-visible:bg-nxm-danger' : 'text-nxm-text' }}"
                    role="menuitem"
                    href="{{ $item['href'] ?? '#' }}"
                >{{ $item['label'] ?? '' }}</a>
            </li>
        @endforeach
        {{ $slot }}
    </ul>
</details>
