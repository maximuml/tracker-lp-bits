@props(['linkExt', 'href', 'target' => false, 'cls', 'inner'])
<a {{ $linkExt }} href="{{ $href }}"@if($target) target="_blank" rel="noopener"@endif class='{{ $cls }}_Name'>{{ $inner }}</a>
