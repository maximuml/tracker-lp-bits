@extends('layouts.nexus_legacy')

@section('title', ($lang['head_avatar_upload'] ?? '') . ' :: ' . ($siteName ?? config('app.name')))

@section('content')
    @php
    $rescaled = ! ($width == $newwidth && $height == $newheight);
    @endphp
    <h1>{{ $lang['std_success'] }}</h1>
    <p>{{ $lang['std_use_following_url'] }}<br><b><a href="{{ $url }}">{{ $url }}</a></b></p>
    <p><a href="/bitbucket-upload.php">{{ $lang['std_upload_another_file'] }}</a>.</p>
    <p><img src="{{ $url }}" border="0"></p>
    <p>{{ $lang['std_image'] }} {{ $rescaled ? $lang['std_rescaled_from'] . $height . ' x ' . $width . $lang['std_to'] . $newheight . ' x ' . $newwidth : $lang['std_need_not_rescaling'] }} {!! $lang['std_profile_updated'] !!}</p>
@endsection
