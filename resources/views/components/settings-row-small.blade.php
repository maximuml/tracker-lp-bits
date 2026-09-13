@props(['label'])
<tr><td width="1%" class="rowhead nowrap" valign="top" align="right">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($label))</td><td width="99%" class="rowfollow" valign="top" align="left">{{ $slot }}</td></tr>
