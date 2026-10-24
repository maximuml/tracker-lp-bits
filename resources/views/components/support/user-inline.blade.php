@props(['tag', 'rainbow', 'inner'])
@if($tag === 'u')<u{{ $rainbow }}>{{ $inner }}</u>@elseif($tag === 'i')<i>{{ $inner }}</i>@else<b{{ $rainbow }}>{{ $inner }}</b>@endif
