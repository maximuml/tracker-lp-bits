@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', __('bitbucketupload.head_avatar_upload'))

@section('content')

    @if ($results !== [])
        <h1>{{ __('bitbucketupload.std_success') }}</h1>
        @foreach ($results as $result)
            <p>{{ __('bitbucketupload.std_use_following_url') }}<br><b><a href="{{ $result['url'] }}">{{ $result['url'] }}</a></b></p>
            <p><img src="{{ $result['url'] }}"></p>
            <p>{{ __('bitbucketupload.std_image') }} {{ (! ($result['width'] == $result['newwidth'] && $result['height'] == $result['newheight'])) ? __('bitbucketupload.std_rescaled_from') . $result['height'] . ' x ' . $result['width'] . __('bitbucketupload.std_to') . $result['newheight'] . ' x ' . $result['newwidth'] : __('bitbucketupload.std_need_not_rescaling') }}</p>
        @endforeach
        <p>{{ __('bitbucketupload.std_bbcode_for_description') }}<br>
            <textarea rows="{{ min(10, count($results) + 1) }}" cols="60" readonly>@foreach ($results as $result)[img]{{ $result['url'] }}[/img]
@endforeach</textarea>
        </p>
        <p>{{ __('bitbucketupload.std_profile_updated') }}</p>
    @endif
    @if ($errors !== [])
        <h1>{{ __('bitbucketupload.std_failed_files') }}</h1>
        <ul>
            @foreach ($errors as $error)
                <li><b>{{ $error['filename'] }}</b>: {{ \App\Support\Html\SafeHtml::fromUntrustedHtml($error['message']) }}</li>
            @endforeach
        </ul>
    @endif
    <p><a href="/web/bitbucket-upload">{{ __('bitbucketupload.std_upload_another_file') }}</a>.</p>
@endsection
