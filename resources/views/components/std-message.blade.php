@props(['heading' => '', 'text' => '', 'htmlstrip' => true])
<table align="center" class="main" width="500" border="0" cellpadding="0" cellspacing="0"><tr><td class="embedded">
@if ($heading)<h2>{{ $htmlstrip ? trim($heading) : $heading }}</h2>@endif
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">@if ($slot->isNotEmpty()){{ $slot }}@else{{ $htmlstrip ? trim($text) : $text }}@endif</td></tr></table></td></tr></table>
