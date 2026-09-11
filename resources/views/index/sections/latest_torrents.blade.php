<h2>{{ $title }}</h2>
<div class="lt-grid">
@foreach($items as $item)
    <div class="lt-card">
        <a class="lt-cover" href="{{ $item['detailsUrl'] }}" title="{{ $item['name'] }}">
            <div class="lt-cover-fallback">{{ $item['nameShort'] }}</div>
            @if($item['thumbUrl'] !== '')
                <img src="{{ $item['thumbUrl'] }}" alt="{{ $item['name'] }}" loading="lazy" />
            @endif
            @if($item['typeLabel'] !== '')
                <span class="lt-type">{{ $item['typeLabel'] }}</span>
            @endif
        </a>
        <div class="lt-title">
            <a href="{{ $item['detailsUrl'] }}"><b>{{ $item['name'] }}</b></a>
        </div>
        <div class="lt-meta">
            <span class="lt-seed" title="{{ $colSeeder }}">&#x25B2; {{ $item['seeders'] }}</span>
            <span class="lt-leech" title="{{ $colLeecher }}">&#x25BC; {{ $item['leechers'] }}</span>
            <span>{{ $item['size'] }}</span>
            <span>{!! $item['ownerHtml'] !!}</span>
        </div>
    </div>
@endforeach
</div>
