<!DOCTYPE html>
<html lang="{{ $chrome->head->locale }}" data-theme="{{ $chrome->head->theme }}" data-fontsize="{{ $chrome->head->fontSize }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
{{ $chrome->head->inlineHeadHtml }}
@if($chrome->head->metaKeywords !== '')
<meta name="keywords" content="{{ $chrome->head->metaKeywords }}" />
@endif
@if($chrome->head->metaDescription !== '')
<meta name="description" content="{{ $chrome->head->metaDescription }}" />
@endif
<meta name="generator" content="{{ PROJECTNAME }}" />
<title>{{ $chrome->head->title }}</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="{{ $chrome->siteName }} Torrents" href="opensearch.php" />
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
@foreach($chrome->head->headStyles as $href)
<link rel="stylesheet" href="{{ $href }}" type="text/css" />
@endforeach
<link rel="stylesheet" href="css/modern.css" type="text/css" />
@if($chrome->head->packThemeUrl !== null)
{{-- Non-Classic stylesheet pack (e.g. Unshatter): loaded after modern.css
     so its palette overrides win the cascade. --}}
<link rel="stylesheet" href="{{ $chrome->head->packThemeUrl }}" type="text/css" />
@endif
@if(file_exists(public_path('css/nxt.css')))
{{-- Tailwind utilities layer for converted components; last in the cascade
     so utility classes beat theme styles. Missing on clones that have not
     run `make css` yet — guarded instead of 404ing. --}}
<link rel="stylesheet" href="css/nxt.css" type="text/css" />
@endif
@livewireStyles
@if($chrome->head->cspNonce !== '')
{{-- CSP nonce bridge: vendored libs (nx-zoom) inject <style> elements at
     runtime; stamp the request nonce on them so nonce-strict
     style-src-elem does not block legitimate styles. Must run before
     the external scripts below. --}}
<script type="text/javascript" nonce="{{ $chrome->head->cspNonce }}">
    (function () {
        var nonce = document.currentScript && document.currentScript.nonce;
        if (!nonce) { return; }
        var stamp = function (n) { if (n && n.nodeName === 'STYLE' && !n.nonce) { n.nonce = nonce; } };
        var ac = Node.prototype.appendChild;
        var ib = Node.prototype.insertBefore;
        Node.prototype.appendChild = function (n) { stamp(n); return ac.call(this, n); };
        Node.prototype.insertBefore = function (n, r) { stamp(n); return ib.call(this, n, r); };
    })();
</script>
@endif
@foreach($chrome->head->headScripts as $src)
<script type="text/javascript" src="{{ $src }}"></script>
@endforeach

@if($chrome->variant !== 'auth')
{{-- nx-layer.js is a <dialog>-based window.layer shim; the auth pages
     deliberately do not ship it (auth-form.js uses plain alert()). --}}
@if($chrome->head->cspNonce !== '')
<script type="text/javascript" nonce="{{ $chrome->head->cspNonce }}">
@else
<script type="text/javascript">
@endif
    window.nexusLayerOptions = {
        confirm: {btnAlign: 'c', title: 'Confirm', btn: ['OK', 'Cancel']},
        alert: {btnAlign: 'c', title: 'Info', btn: ['OK', 'Cancel']}
    }
</script>
<script type="text/javascript" src="js/nx-layer.js"></script>
@endif
@foreach (\App\Support\AssetAppender::getAppendHeadersSafe() as $html)
{{ $html }}
@endforeach
</head>
