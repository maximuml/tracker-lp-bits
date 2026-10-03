@props(['id', 'filename', 'url', 'onclick', 'sizeLabel', 'sizeText', 'timeText'])
<img id="attach{{ $id }}" alt="{{ $filename }}" src="{{ $url }}"{{ $onclick }} data-domtt-promo /><template class="nx-tt"><strong>{{ $sizeLabel }}</strong>: {{ $sizeText }}<br />{{ $timeText }}</template>
