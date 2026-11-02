@props(['url', 'langFolder'])
<img src="{{ \App\Support\Url::rootRelative($url) }}" alt="avatar" width="150px" data-avatar-check="{{ $langFolder }}" />
