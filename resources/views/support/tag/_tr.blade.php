@props(['classAttr', 'cells'])
@if($cells === [])<tr></tr>@else<tr>@foreach($cells as $cell)<td{{ $classAttr }}>{{ $cell }}</td>@endforeach</tr>@endif
