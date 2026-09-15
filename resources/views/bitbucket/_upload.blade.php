<h1>{{ $lang['text_avatar_upload'] }}</h1>
<form method="post" action="/bitbucket-upload" enctype="multipart/form-data">
<div class="nx-fgrid">
    @if (! is_writable(ROOT_PATH . $bitbucket))
        <div class="nx-ffull">{{ $lang['text_upload_directory_unwritable'] }}</div>
    @endif
    <div class="nx-ffull">{{ $lang['text_disclaimer'] }}{{ $scaleHeight }}{{ $lang['text_disclaimer_two'] }}{{ $scaleWidth }}@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_disclaimer_three'])){{ number_format($maxFileSize) }}{{ $lang['text_disclaimer_four'] }}</div>
        <div class="nx-fhead">{{ $lang['row_file'] }}</div>
        <div class="nx-fcell"><input type="file" name="file" size="60"></div>
        <div class="nx-ffull nx-toolbox">
            <input class="checkbox" type="checkbox" name="public" value="yes"> {{ $lang['checkbox_avatar_shared'] }}
            <input type="submit" value="{{ $lang['submit_upload'] }}">
        </div>
</div>
</form>
