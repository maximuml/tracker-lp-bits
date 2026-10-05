<nav class="my-2.5 text-center" aria-label="{{ ($top ?? false) ? 'Pagination top' : 'Pagination bottom' }}">
    <ul class="m-0 flex list-none flex-wrap justify-center gap-0.5 p-0">
        <li>
            @if ($vm->prevUrl !== null)<a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $vm->prevUrl }}" rel="prev" title="{{ $vm->prevTitle }}">&lsaquo; {{ $vm->prevLabel }}</a>
            @else<span class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-text-dim">&lsaquo; {{ $vm->prevLabel }}</span>@endif
        </li>
        @foreach ($vm->links as $link)
            @if ($link->dots)
            <li class="px-1.5 py-0.5 text-nxm-text-dim" aria-hidden="true">…</li>
            @else
            <li>
                @if ($link->url !== null)<a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $link->url }}" title="{{ $link->start }} - {{ $link->end }}">{{ $link->num }}</a>
                @else<span class="inline-block border border-nxm-accent bg-nxm-accent px-2 py-0.5 font-bold text-nxm-on-accent" aria-current="page" title="{{ $link->start }} - {{ $link->end }}">{{ $link->num }}</span>@endif
            </li>
            @endif
        @endforeach
        <li>
            @if ($vm->nextUrl !== null)<a class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-accent-text no-underline" href="{{ $vm->nextUrl }}" rel="next" title="{{ $vm->nextTitle }}">{{ $vm->nextLabel }} &rsaquo;</a>
            @else<span class="inline-block border border-nxm-border px-2 py-0.5 text-nxm-text-dim">{{ $vm->nextLabel }} &rsaquo;</span>@endif
        </li>
    </ul>
</nav>
