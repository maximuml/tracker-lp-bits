@extends('layouts.legacy')

@section('title', $lang['head_original_comment'] ?? 'Original comment')

@section('content')
<h1>{{ $lang['text_original_content_of_comment'] ?? 'Original contents of comment ' }}{{ $commentId }}</h1>
<table width="737" border="1" cellspacing="0" cellpadding="5">
<tr><td class="text">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Comment::format((string) $arr['ori_text'])))
</td></tr></table>
@if ($returnto)
<p><font size="small">(<a href="{{ $returnto }}">{{ $lang['text_back'] ?? 'back' }}</a>)</font></p>
@endif
@endsection
