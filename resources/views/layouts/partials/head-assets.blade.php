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
@foreach($chrome->headScripts as $src)
<script type="text/javascript" src="{{ $src }}"></script>
@endforeach
<script type="text/javascript" src="vendor/jquery-3.7.1.min.js"></script>
@if($chrome->variant !== 'auth')
{{-- layer.js + its options are the legacy dialog toolkit; the auth pages
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
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js"></script>
@endif
@foreach (\App\Support\AssetAppender::getAppendHeadersSafe() as $html)
{{ $html }}
@endforeach
</head>
