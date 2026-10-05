@if($grid)
<div class="nx-fhead">{{ $label }}</div><div class="nx-fcell">{{ $slot }}</div>
@else
<tr><td class="whitespace-nowrap align-top px-2.5 py-1.5 text-right font-semibold text-nxm-text-dim">{{ $label }}</td><td class="align-top px-2.5 py-1.5">{{ $slot }}</td></tr>
@endif
