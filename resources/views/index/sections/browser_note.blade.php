@if($browserNote['show'])
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<div align="center"><br /><font class="medium">{{ $browserNote['note'] }}</font></div>
<div align="center"><a href="{{ $browserNote['nexusUrl'] }}" title="{{ $browserNote['projectName'] }}" target="_blank"><img src="pic/nexus.png" alt="{{ $browserNote['projectName'] }}" /></a></div>
</td></tr></table>
@endif
