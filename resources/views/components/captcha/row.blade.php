@if($grid)
<div class="nx-fhead">{{ $label }}</div><div class="nx-fcell">{{ $slot }}</div>
@else
<tr><td class="rowhead">{{ $label }}</td><td>{{ $slot }}</td></tr>
@endif
