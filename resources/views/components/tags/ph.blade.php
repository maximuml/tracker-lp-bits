@foreach ($parts as $i => $part)@if ($i % 2 === 1)<i>{{ $part }}</i>@else{{ $part }}@endif@endforeach
