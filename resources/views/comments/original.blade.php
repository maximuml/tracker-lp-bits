@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('comment.head_original_comment'))

@section('content')
<h1>{{ __('comment.text_original_content_of_comment')}}{{ $commentId }}</h1>
<div class="nx-box nx-box--737">
{{ \App\Support\Comment::format((string) $arr['ori_text']) }}
</div>
@if ($returnto)
<p><span>(<a href="{{ $returnto }}">{{ __('comment.text_back')}}</a>)</span></p>
@endif
@endsection
