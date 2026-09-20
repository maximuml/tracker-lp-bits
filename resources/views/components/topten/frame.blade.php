@props(['caption'])
@if ((string) $caption !== '')<h2 align="left">{{ $caption }}</h2>@endif
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text"  align="center">
<table class="main" border="1" cellspacing="0" cellpadding="5">{{ $slot }}</table>
</td></tr></table>
