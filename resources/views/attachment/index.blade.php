@php
$lang_attachment = (array) (\app(\App\Support\Globals::class)->get('lang_attachment') ?? []);
$CURUSER = (array) (\app(\App\Support\CurrentUser::class)->get() ?? []);
$Attach = $Attach ?? new \App\Support\Attachment\AttachmentService($CURUSER['id'] ?? 0);
$count_limit = (int) ($count_limit ?? $Attach->get_count_limit());
$count_left = $count_left ?? $Attach->get_count_left();
$size_limit = $size_limit ?? $Attach->get_size_limit_byte();
$allowed_exts = $allowed_exts ?? $Attach->get_allowed_ext();
$css_uri = $css_uri ?? \App\Support\Style::cssUriWithContext();
$altsize = (string) ($altsize ?? '');
$callback_func = (string) ($callback_func ?? '');
$warning = (string) ($warning ?? '');
$script = (string) ($script ?? '');
// The upload callback arrives as a <script> block from AttachmentMutationService;
// the nonce-strict CSP requires adding the per-request nonce before output.
$cspNonce = (string) ($cspNonce ?? '');
if ($script !== '' && $cspNonce !== '') {
    $script = (string) preg_replace('/<script(?![^>]*\snonce=)/i', '<script nonce="'.htmlspecialchars($cspNonce, ENT_QUOTES).'"', $script);
}
@endphp
<html>
<head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="{{ \App\Support\Style::fontCssUriWithContext() }}" type="text/css">
<link rel="stylesheet" href="{{ $css_uri.'theme.css' }}" type="text/css">
</head>
<body class="inframe">
<table width="100%">
{!! $script !!}
@if ($Attach->enable_attachment())
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
        @php
            $allowedextsblock = '';
            foreach ($allowed_exts as $ext) {
                $allowedextsblock .= $ext.'/';
            }
            $allowedextsblock = rtrim(trim($allowedextsblock), '/');
            if (! $allowedextsblock) {
                $allowedextsblock = 'N/A';
            }
        @endphp
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
