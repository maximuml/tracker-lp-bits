@props(['class', 'cells'])
@if($cells === [])<tr></tr>@else<tr>@foreach($cells as $cell)@if($class !== '')<td class="{{ $class }}">{{ $cell }}</td>@else<td>{{ $cell }}</td>@endif@endforeach</tr>@endif
