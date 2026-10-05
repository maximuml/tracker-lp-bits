<div class="nx-main mx-auto nx-box--500">
@if ($heading)<h2>{{ $htmlstrip ? trim((string) $heading) : new \Illuminate\Support\HtmlString((string) $heading) }}</h2>@endif
<div class="nx-box">{{ $body ?? ($htmlstrip ? trim((string) $text) : new \Illuminate\Support\HtmlString((string) $text)) }}</div></div>
