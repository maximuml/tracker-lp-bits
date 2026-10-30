@props(['imgId', 'src', 'enableResizer', 'maxWidth', 'maxHeight'])
<img id="{{ $imgId }}" alt="image" src="{{ $src }}"@if($enableResizer) data-scale="{{ $maxWidth }}x{{ $maxHeight }}" data-zoomable @endif data-img-fallback="{{ $src }}"  loading="lazy" decoding="async" />
