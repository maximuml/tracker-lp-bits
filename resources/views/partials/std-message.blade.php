<table class="main nx-mx-auto nx-box--500"><tr><td class="embedded">
@if ($heading)<h2>{{ $htmlstrip ? trim((string) $heading) : new \Illuminate\Support\HtmlString((string) $heading) }}</h2>@endif
<div class="nx-box">{{ $body ?? ($htmlstrip ? trim((string) $text) : new \Illuminate\Support\HtmlString((string) $text)) }}</div></td></tr></table>
