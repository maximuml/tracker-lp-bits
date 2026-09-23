<div {{ $attributes->merge(['class' => 'nx-error-summary']) }} role="alert" tabindex="-1">
    <p class="nx-error-summary__title">{{ $title !== '' ? $title : __('legacy/index.error_summary_title') }}</p>
    <ul class="nx-error-summary__list">
        @foreach ($items as $item)
            <li>
                @if ($item['href'] !== null)
                    <a href="{{ $item['href'] }}">{{ $item['message'] }}</a>
                @else
                    {{ $item['message'] }}
                @endif
            </li>
        @endforeach
    </ul>
</div>
