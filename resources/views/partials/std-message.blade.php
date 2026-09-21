<table class="main nx-mx-auto nx-box--500" border="0" cellpadding="0" cellspacing="0"><tr><td class="embedded">
@if ($heading)<h2>{{ $htmlstrip ? trim((string) $heading) : new \Illuminate\Support\HtmlString((string) $heading) }}</h2>@endif
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text">{{ $body ?? ($htmlstrip ? trim((string) $text) : new \Illuminate\Support\HtmlString((string) $text)) }}</td></tr></table></td></tr></table>
