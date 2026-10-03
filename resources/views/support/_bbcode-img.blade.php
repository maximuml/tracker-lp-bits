@props(['imgId', 'escapedSrc', 'resizerAttrs'])
<img id="{{ $imgId }}" alt="image" src="{{ $escapedSrc }}"{{ $resizerAttrs }} data-img-fallback="{{ $escapedSrc }}" />
