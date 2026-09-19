@props(['page', 'pages', 'href', 'items', 'label' => 'Pagination'])
{{-- Forum listings keep the legacy 0-based `page=` query param; `items`
     is the 1-based display window precomputed by the view model. --}}
@if ($pages > 1)
<nav class="nx-pagination" aria-label="{{ $label }}">
    <ul class="nx-pagination__list">
        @if ($page > 0)
            <li class="nx-pagination__item">
                <a class="nx-pagination__link" href="{{ $href }}page={{ $page - 1 }}" rel="prev">{{ __('legacy/functions.text_prev') }}</a>
            </li>
        @endif
        @foreach ($items as $item)
            @if ($item === '…')
                <li class="nx-pagination__item nx-pagination__item--gap" aria-hidden="true">…</li>
            @else
                <li class="nx-pagination__item">
                    @if ($item === $page + 1)
                        <span class="nx-pagination__link nx-pagination__link--current" aria-current="page">{{ $item }}</span>
                    @else
                        <a class="nx-pagination__link" href="{{ $href }}page={{ $item - 1 }}">{{ $item }}</a>
                    @endif
                </li>
            @endif
        @endforeach
        @if ($page < $pages - 1)
            <li class="nx-pagination__item">
                <a class="nx-pagination__link" href="{{ $href }}page={{ $page + 1 }}" rel="next">{{ __('legacy/functions.text_next') }}</a>
            </li>
        @endif
    </ul>
</nav>
@endif
