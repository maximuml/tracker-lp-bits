@foreach ($vm->links as $link)@if (! $loop->first) | @endif@if ($link->dots)...@elseif ($link->url !== null)<a href="{{ $link->url }}"><b>{{ $link->start }}&nbsp;-&nbsp;{{ $link->end }}</b></a>@else<span class="gray"><b>{{ $link->start }}&nbsp;-&nbsp;{{ $link->end }}</b></span>@endif
@endforeach
