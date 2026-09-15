@extends('layouts.legacy')

@section('title', $lang['head_original_comment'] ?? 'Original comment')

@section('content')
<h1>{{ $lang['text_original_content_of_comment'] ?? 'Original contents of comment ' }}{{ $commentId }}</h1>
<div class="nx-box nx-box--737">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Comment::format((string) $arr['ori_text'])))
</div>
@if ($returnto)
<p><font size="small">(<a href="{{ $returnto }}">{{ $lang['text_back'] ?? 'back' }}</a>)</font></p>
@endif
@endsection
