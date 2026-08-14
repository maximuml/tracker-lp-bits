@php
$lang_userhistory = (array) (\App\Support\SupportContext::getGlobal('lang_userhistory') ?? []);
$title = match ($action ?? '') {
    'viewposts' => $lang_userhistory['head_posts_history'] ?? 'Posts history',
    'viewcomments' => $lang_userhistory['head_comments_history'] ?? 'Comments history',
    default => $lang_userhistory['head_user_history'] ?? 'User history',
};
@endphp
@extends('layouts.legacy')

@section('title', $title)

@section('content')
@include('userhistory._userhistory')
@endsection
