@extends('layouts.app', ['chromeVariant' => 'legacy', 'shell' => 'bare'])

@section('title', __('legacy/bitbucketupload.head_avatar_upload'))

@section('content')

    <h1>{{ __('legacy/bitbucketupload.std_success') }}</h1>
    <p>{{ __('legacy/bitbucketupload.std_use_following_url') }}<br><b><a href="{{ $url }}">{{ $url }}</a></b></p>
    <p><a href="/bitbucket-upload.php">{{ __('legacy/bitbucketupload.std_upload_another_file') }}</a>.</p>
    <p><img src="{{ $url }}"></p>
    <p>{{ __('legacy/bitbucketupload.std_image') }} {{ (! ($width == $newwidth && $height == $newheight)) ? __('legacy/bitbucketupload.std_rescaled_from') . $height . ' x ' . $width . __('legacy/bitbucketupload.std_to') . $newheight . ' x ' . $newwidth : __('legacy/bitbucketupload.std_need_not_rescaling') }} <br />{{ __('legacy/bitbucketupload.std_profile_updated') }}</p>
@endsection
