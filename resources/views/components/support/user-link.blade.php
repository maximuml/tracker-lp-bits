@props(['linkExt', 'href', 'target' => false, 'cls', 'inner'])
<a {{ $linkExt }} href="{{ $href }}"@if($target) target="_blank"@endif class='{{ $cls }}_Name'>{{ $inner }}</a>
