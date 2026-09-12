@if ($pages > 1)
<nav {{ $attributes->merge(['class' => 'nx-pagination']) }} aria-label="{{ $label }}">
    <ul class="nx-pagination__list">
        @if ($page > 1)
            <li class="nx-pagination__item">
                <a class="nx-pagination__link" href="{{ $url($page - 1) }}" rel="prev">{{ $prevLabel }}</a>
            </li>
        @endif
        @foreach ($items as $item)
            @if ($item === '…')
                <li class="nx-pagination__item nx-pagination__item--gap" aria-hidden="true">…</li>
            @else
                <li class="nx-pagination__item">
                    @if ($item === $page)
                        <span class="nx-pagination__link nx-pagination__link--current" aria-current="page">{{ $item }}</span>
                    @else
                        <a class="nx-pagination__link" href="{{ $url($item) }}">{{ $item }}</a>
                    @endif
                </li>
            @endif
        @endforeach
        @if ($page < $pages)
            <li class="nx-pagination__item">
                <a class="nx-pagination__link" href="{{ $url($page + 1) }}" rel="next">{{ $nextLabel }}</a>
            </li>
        @endif
    </ul>
</nav>
@endif
