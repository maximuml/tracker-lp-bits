<h1>{{ __('legacy/bitbucketupload.text_avatar_upload') }}</h1>
<form method="post" action="/bitbucket-upload" enctype="multipart/form-data">
<div class="nx-fgrid">
    @if (! is_writable(ROOT_PATH . $bitbucket))
        <div class="nx-ffull">{{ __('legacy/bitbucketupload.text_upload_directory_unwritable') }}</div>
    @endif
    <div class="nx-ffull">{{ __('legacy/bitbucketupload.text_disclaimer') }}{{ $scaleHeight }}{{ __('legacy/bitbucketupload.text_disclaimer_two') }}{{ $scaleWidth }} {{ __('legacy/bitbucketupload.text_disclaimer_three') }}<br />{{ __('legacy/bitbucketupload.text_max_file_size') }} {{ number_format($maxFileSize) }}{{ __('legacy/bitbucketupload.text_disclaimer_four') }}</div>
        <div class="nx-fhead">{{ __('legacy/bitbucketupload.row_file') }}</div>
        <div class="nx-fcell"><input type="file" name="file" size="60"></div>
        <div class="nx-ffull nx-toolbox">
            <input class="checkbox" type="checkbox" name="public" value="yes"> {{ __('legacy/bitbucketupload.checkbox_avatar_shared') }}
            <input type="submit" value="{{ __('legacy/bitbucketupload.submit_upload') }}">
        </div>
</div>
</form>
