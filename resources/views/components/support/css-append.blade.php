@props(['src' => null, 'content' => null, 'nonce' => null])
@if($src !== null)<link rel="stylesheet" href="{{ \App\Support\AssetAppender::versionedSrc($src) }}" type="text/css">@else<style type="text/css" nonce="{{ $nonce }}">{{ $content }}</style>@endif
