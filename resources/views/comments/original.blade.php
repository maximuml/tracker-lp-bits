@extends('layouts.legacy')

@section('title', __('legacy/comment.head_original_comment'))

@section('content')
<h1>{{ __('legacy/comment.text_original_content_of_comment')}}{{ $commentId }}</h1>
<div class="nx-box nx-box--737">
{{ \App\Support\Comment::format((string) $arr['ori_text']) }}
</div>
@if ($returnto)
<p><font size="small">(<a href="{{ $returnto }}">{{ __('legacy/comment.text_back')}}</a>)</font></p>
@endif
@endsection
