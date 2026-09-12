@props(['label'])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">{{ $label }}</td>
    <td class="rowfollow" valign="top" align="left">{{ $slot }}</td>
</tr>
