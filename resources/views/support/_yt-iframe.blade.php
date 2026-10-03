@props(['width', 'height', 'videoId'])
<iframe width="{{ $width }}" height="{{ $height }}" src="https://www.youtube.com/embed/{{ $videoId }}" title="YouTube video player" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
