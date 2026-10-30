@props(['id', 'filename', 'url', 'imageResizer', 'sizeLabel', 'sizeText', 'timeText'])
<img id="attach{{ $id }}" alt="{{ $filename }}" src="{{ $url }}"@if($imageResizer) data-zoomable data-zoom-src="{{ $url }}"@endif data-domtt-promo  loading="lazy" decoding="async" /><template class="nx-tt"><strong>{{ $sizeLabel }}</strong>: {{ $sizeText }}<br />{{ $timeText }}</template>
