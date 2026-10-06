<html data-theme="{{ $theme }}" data-fontsize="{{ $fontSize }}">
<head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ $css_uri.'theme.css' }}" type="text/css">
<link rel="stylesheet" href="css/modern.css" type="text/css">
</head>
<body class="inframe">
<div>
{{ $script ?? '' }}
@if ($enableAttachment ?? false)
    <form enctype="multipart/form-data" name="attachment" method="post" action="/web/attachments/upload?callback_func={{ $callback_func }}">
    @csrf
    <div class="nx-attach-controls">
    <input type="file" name="file[]" multiple aria-label="{{ __('legacy/attachment.submit_upload') }}" @if (! $count_left) disabled="disabled"@endif />
    <label><input type="checkbox" name="altsize" value="yes"@if ($altsize == 'yes') checked="checked"@endif /> {{ __('legacy/attachment.text_small_thumbnail')}}</label>
    <input type="submit" class="nx-postbtn" name="submit" value="{{ __('legacy/attachment.submit_upload')}}"@if (! $count_left) disabled="disabled"@endif />
    </div>
    <div class="nx-attach-info">
    @if ($warning)
        <span class="striking">{{ $warning }}</span>
    @else
        <b>{{ __('legacy/attachment.text_left')}}</b><span class="text-nxm-danger">{{ $count_left }}</span>{{ __('legacy/attachment.text_of')}}{{ $count_limit }}&nbsp;&nbsp;<b>{{ __('legacy/attachment.text_size_limit')}}</b>{{ \App\Support\Format::size($size_limit) }}&nbsp;&nbsp;<b>{{ __('legacy/attachment.text_file_extensions')}}</b>
        <span title="{{ $allowedextsblock }}"><i>{{ __('legacy/attachment.text_mouse_over_here')}}</i></span>
    @endif
    </div>
    </form>
@endif
</div>
</body>
</html>
