<h1>{{ __('bitbucketupload.text_avatar_upload') }}</h1>
<form method="post" action="/bitbucket-upload" enctype="multipart/form-data">
<div class="nx-fgrid">
    @if (! $bucketWritable)
        <div class="nx-ffull">{{ __('bitbucketupload.text_upload_directory_unwritable') }}</div>
    @endif
    <div class="nx-ffull"><b>{{ __('bitbucketupload.text_disclaimer_title') }}</b><br />{{ __('bitbucketupload.text_disclaimer') }}<br />{{ __('bitbucketupload.text_disclaimer_pd') }}<br />{{ __('bitbucketupload.text_disclaimer_scale') }}{{ $scaleHeight }}{{ __('bitbucketupload.text_disclaimer_two') }}{{ $scaleWidth }} {{ __('bitbucketupload.text_disclaimer_three') }}<br />{{ __('bitbucketupload.text_max_file_size') }} {{ number_format($maxFileSize) }}{{ __('bitbucketupload.text_disclaimer_four') }}</div>
        <div class="nx-fhead">{{ __('bitbucketupload.row_files') }}</div>
        <div class="nx-fcell"><input type="file" name="file[]" size="60" multiple><br>{{ __('bitbucketupload.text_select_multiple') }}</div>
        <div class="nx-ffull nx-toolbox">
            <input class="checkbox" type="checkbox" name="public" value="yes"> {{ __('bitbucketupload.checkbox_avatar_shared') }}
            <input type="submit" value="{{ __('bitbucketupload.submit_upload') }}">
        </div>
</div>
</form>
