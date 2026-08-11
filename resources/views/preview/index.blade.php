@php
$body = \App\Support\SupportContext::getPost('body');
@endphp
<table width="100%" border="1" cellspacing="0" cellpadding="10" align="left">
    <tr><td align="left">{!! \App\Support\Format::formatComment($body) !!}<br /><br /></td></tr>
</table>
