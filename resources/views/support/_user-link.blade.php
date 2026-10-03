@props(['linkExt', 'href', 'targetAttr', 'cls', 'inner'])
<a {{ $linkExt }} href="{{ $href }}"{{ $targetAttr }} class='{{ $cls }}_Name'>{{ $inner }}</a>
