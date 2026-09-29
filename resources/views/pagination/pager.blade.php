<nav class="nexus-pagination nx-pagination nx-center" aria-label="Pagination">
    <ul class="nx-pagination__list">
        <li class="nx-pagination__item">
            @if ($vm->prevUrl !== null)<a class="nx-pagination__link" href="{{ $vm->prevUrl }}" rel="prev" title="{{ $vm->prevTitle }}">&lsaquo; {{ $vm->prevLabel }}</a>
            @else<span class="nx-pagination__link nx-pagination__link--disabled">&lsaquo; {{ $vm->prevLabel }}</span>@endif
        </li>
        @foreach ($vm->links as $link)
            @if ($link->dots)
            <li class="nx-pagination__item nx-pagination__item--gap" aria-hidden="true">…</li>
            @else
            <li class="nx-pagination__item">
                @if ($link->url !== null)<a class="nx-pagination__link" href="{{ $link->url }}" title="{{ $link->start }} - {{ $link->end }}">{{ $link->num }}</a>
                @else<span class="nx-pagination__link nx-pagination__link--current" aria-current="page" title="{{ $link->start }} - {{ $link->end }}">{{ $link->num }}</span>@endif
            </li>
            @endif
        @endforeach
        <li class="nx-pagination__item">
            @if ($vm->nextUrl !== null)<a class="nx-pagination__link" href="{{ $vm->nextUrl }}" rel="next" title="{{ $vm->nextTitle }}">{{ $vm->nextLabel }} &rsaquo;</a>
            @else<span class="nx-pagination__link nx-pagination__link--disabled">{{ $vm->nextLabel }} &rsaquo;</span>@endif
        </li>
    </ul>
</nav>
