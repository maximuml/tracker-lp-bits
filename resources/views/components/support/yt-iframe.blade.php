@props(['width', 'height', 'videoId'])
<div class="nx-ytfacade" data-yt-id="{{ $videoId }}" data-yt-width="{{ (int) $width }}" data-yt-height="{{ (int) $height }}" role="group" aria-label="{{ __('functions.yt_video_attr') }}">
    <button type="button" class="nx-ytfacade__play" aria-label="{{ __('functions.yt_play_attr') }}">
        <span class="nx-ytfacade__badge" aria-hidden="true">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
        </span>
        <span class="nx-ytfacade__label">{{ __('functions.yt_click_load') }}</span>
    </button>
</div>
