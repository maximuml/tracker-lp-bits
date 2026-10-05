@props(['src' => null, 'content' => null, 'nonce' => null])
@if($src !== null)<script type="text/javascript" src="{{ \App\Support\AssetAppender::versionedSrc($src) }}"></script>@else<script type="text/javascript" nonce="{{ $nonce }}">{{ $content }}</script>@endif
