@props(['label'])
<tr>
    <td class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($label))</td>
    <td class="rowfollow" valign="top" align="left">{{ $slot }}</td>
</tr>
