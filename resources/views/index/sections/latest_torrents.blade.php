<h2>{{ $title }}</h2>
<style nonce="{{ $cspNonce ?? '' }}">
    .lt-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin: 8px 0 16px;
    }
    .lt-grid .lt-card {
        border: 1px solid rgba(127,127,127,.35);
        border-radius: 6px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        background: rgba(127,127,127,.05);
    }
    .lt-grid .lt-cover {
        position: relative;
        display: block;
        width: 100%;
        aspect-ratio: 2 / 3;
        max-height: 240px;
        background: rgba(0,0,0,.08);
        overflow: hidden;
    }
    .lt-grid .lt-cover img,
    .lt-grid .lt-cover .lt-cover-fallback {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
    .lt-grid .lt-cover-fallback {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 8px;
        box-sizing: border-box;
        font-size: 12px;
        line-height: 1.3;
        color: rgba(127,127,127,.85);
        text-align: center;
        word-break: break-word;
    }
    .lt-grid .lt-type {
        position: absolute;
        top: 6px;
        right: 6px;
        background: rgba(0,0,0,.78);
        color: #fff;
        font-size: 11px;
        font-weight: bold;
        padding: 2px 6px;
        border-radius: 3px;
        line-height: 1.2;
        letter-spacing: .3px;
        pointer-events: none;
    }
    .lt-grid .lt-title {
        padding: 6px 8px 4px;
        font-size: 12px;
        line-height: 1.3;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }
    .lt-grid .lt-title a { text-decoration: none; }
    .lt-grid .lt-meta {
        margin-top: auto;
        display: flex;
        flex-wrap: wrap;
        gap: 4px 10px;
        padding: 6px 8px;
        font-size: 11px;
        border-top: 1px solid rgba(127,127,127,.2);
    }
    .lt-grid .lt-seed { color: #2fad2f; font-weight: bold; }
    .lt-grid .lt-leech { color: #d04848; font-weight: bold; }
    @media (max-width: 700px) {
        .lt-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 420px) {
        .lt-grid { grid-template-columns: 1fr; }
    }
</style>
<div class="lt-grid">
@foreach($items as $item)
    <div class="lt-card">
        <a class="lt-cover" href="{{ htmlspecialchars($item['detailsUrl']) }}" title="{{ $item['nameSafe'] }}">
            @if($item['thumbUrl'] !== '')
                <img src="{{ htmlspecialchars($item['thumbUrl']) }}" alt="{{ $item['nameSafe'] }}" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.style.display='flex';}" />
                <div class="lt-cover-fallback" style="display:none;">{{ $item['nameShort'] }}</div>
            @else
                <div class="lt-cover-fallback">{{ $item['nameShort'] }}</div>
            @endif
            @if($item['typeLabel'] !== '')
                <span class="lt-type">{{ htmlspecialchars($item['typeLabel']) }}</span>
            @endif
        </a>
        <div class="lt-title">
            <a href="{{ htmlspecialchars($item['detailsUrl']) }}"><b>{{ $item['nameSafe'] }}</b></a>
        </div>
        <div class="lt-meta">
            <span class="lt-seed" title="{{ htmlspecialchars($colSeeder) }}">&#x25B2; {{ $item['seeders'] }}</span>
            <span class="lt-leech" title="{{ htmlspecialchars($colLeecher) }}">&#x25BC; {{ $item['leechers'] }}</span>
            <span>{{ $item['size'] }}</span>
            <span>{!! $item['ownerHtml'] !!}</span>
        </div>
    </div>
@endforeach
</div>
