<html>
<head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ \App\Support\Style::fontCssUriWithContext() }}" type="text/css">
<link rel="stylesheet" href="{{ $css_uri.'theme.css' }}" type="text/css">
</head>
<body class="inframe">
<table width="100%">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($script ?? ''))
@if ($enableAttachment ?? false)
    <form enctype="multipart/form-data" name="attachment" method="post" action="attachment.php?callback_func={{ $callback_func }}">
    @csrf
    <tr>
    <td class="embedded" colspan="2" align=left>
    <input type="file" name="file"@if (! $count_left) disabled="disabled"@endif />&nbsp;
    <input type="checkbox" name="altsize" value="yes"@if ($altsize == 'yes') checked="checked"@endif />{{ $lang_attachment['text_small_thumbnail'] ?? '' }}&nbsp;
    <input type="submit" name="submit" value="{{ $lang_attachment['submit_upload'] ?? '' }}"@if (! $count_left) disabled="disabled"@endif />
    @if ($warning)
        <span class="striking">{{ $warning }}</span>
    @else
        <b>{{ $lang_attachment['text_left'] ?? '' }}</b><font color="red">{{ $count_left }}</font>{{ $lang_attachment['text_of'] ?? '' }}{{ $count_limit }}&nbsp;&nbsp;&nbsp;<b>{{ $lang_attachment['text_size_limit'] ?? '' }}</b>{{ \App\Support\Format::size($size_limit) }}&nbsp;&nbsp;&nbsp;<b>{{ $lang_attachment['text_file_extensions'] ?? '' }}</b>
        <span title="{{ $allowedextsblock }}"><i>{{ $lang_attachment['text_mouse_over_here'] ?? '' }}</i></span>
    @endif
    </td>
    </tr>
    </form>
@endif
</table>
</body>
</html>
