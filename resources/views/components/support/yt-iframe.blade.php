@props(['width', 'height', 'videoId'])
<div class="nx-ytfacade" data-yt-id="{{ $videoId }}" data-yt-width="{{ (int) $width }}" data-yt-height="{{ (int) $height }}" role="group" aria-label="YouTube video">
    <button type="button" class="nx-ytfacade__play" aria-label="Play YouTube video">
        <span class="nx-ytfacade__badge" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </span>
        <span class="nx-ytfacade__label">YouTube — click to load</span>
    </button>
</div>
