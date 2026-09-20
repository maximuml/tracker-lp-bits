<!DOCTYPE html>
<html lang="{{ $chrome->locale }}" data-theme="{{ $chrome->theme }}" data-fontsize="{{ $chrome->fontSize }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
{{ $chrome->inlineHeadHtml }}
@if($chrome->metaKeywords !== '')
<meta name="keywords" content="{{ $chrome->metaKeywords }}" />
@endif
@if($chrome->metaDescription !== '')
<meta name="description" content="{{ $chrome->metaDescription }}" />
@endif
<meta name="generator" content="{{ PROJECTNAME }}" />
<title>{{ $chrome->title }}</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="{{ $chrome->siteName }} Torrents" href="opensearch.php" />
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
@foreach($chrome->headStyles as $href)
<link rel="stylesheet" href="{{ $href }}" type="text/css" />
@endforeach
<link rel="stylesheet" href="css/modern.css" type="text/css" />
@if($chrome->cspNonce !== '')
{{-- CSP nonce bridge: vendored libs (nx-zoom) inject <style> elements at
     runtime; stamp the request nonce on them so nonce-strict
     style-src-elem does not block legitimate styles. Must run before
     the external scripts below. --}}
<script type="text/javascript" nonce="{{ $chrome->cspNonce }}">
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
@foreach($chrome->headScripts as $src)
<script type="text/javascript" src="{{ $src }}"></script>
@endforeach
<script type="text/javascript" src="vendor/jquery-3.7.1.min.js"></script>
@if($chrome->variant !== 'auth')
{{-- nx-layer.js is a <dialog>-based window.layer shim; the auth pages
     deliberately do not ship it (auth-form.js uses plain alert()). --}}
@if($chrome->cspNonce !== '')
<script type="text/javascript" nonce="{{ $chrome->cspNonce }}">
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
