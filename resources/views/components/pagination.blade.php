@if ($pages > 1)
<nav {{ $attributes->merge(['class' => 'my-2.5']) }} aria-label="{{ $label }}">
    <ul class="m-0 flex list-none flex-wrap gap-0.5 p-0">
        @if ($page > 1)
            <li>
                <a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $url($page - 1) }}" rel="prev">{{ $prevLabel }}</a>
            </li>
        @endif
        @foreach ($items as $item)
            @if ($item === '…')
                <li class="px-1.5 py-0.5 text-nxm-text-dim" aria-hidden="true">…</li>
            @else
                <li>
                    @if ($item === $page)
                        <span class="inline-block border border-nxm-accent bg-nxm-accent px-2 py-0.5 font-bold text-nxm-on-accent" aria-current="page">{{ $item }}</span>
                    @else
                        <a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $url($item) }}">{{ $item }}</a>
                    @endif
                </li>
            @endif
        @endforeach
        @if ($page < $pages)
            <li>
                <a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $url($page + 1) }}" rel="next">{{ $nextLabel }}</a>
            </li>
        @endif
    </ul>
</nav>
@endif
