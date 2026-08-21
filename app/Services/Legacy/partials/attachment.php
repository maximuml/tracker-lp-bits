<?php

use App\Support\Attachment\AttachmentService;
use App\Support\Format;
use App\Support\Style;
use App\Support\SupportContext;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($lang_attachment)) {
    $lang_attachment = (array) (SupportContext::getGlobal('lang_attachment') ?? []);
}

// Variables are precomputed by UtilityController::attachment.
$Attach = $Attach ?? new AttachmentService($CURUSER['id'] ?? 0);
$count_limit = $count_limit ?? (int) $Attach->get_count_limit();
$count_left = $count_left ?? $Attach->get_count_left();
$size_limit = $size_limit ?? $Attach->get_size_limit_byte();
$allowed_exts = $allowed_exts ?? $Attach->get_allowed_ext();
$css_uri = $css_uri ?? Style::cssUriWithContext();
$altsize = $altsize ?? '';
$callback_func = $callback_func ?? '';
$warning = $warning ?? '';
$script = $script ?? '';
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo Style::fontCssUriWithContext()?>" type="text/css">
<link rel="stylesheet" href="<?php echo $css_uri.'theme.css'?>" type="text/css">
</head>
<body class="inframe">
<table width="100%">
<?php
echo $script;
if ($Attach->enable_attachment()) {
    echo '<form enctype="multipart/form-data" name="attachment" method="post" action="attachment.php?callback_func='.htmlspecialchars($callback_func).'">';
    echo '<tr>';
    echo '<td class="embedded" colspan="2" align=left>';
    echo '<input type="file" name="file"'.($count_left ? '' : ' disabled="disabled"').' />&nbsp;';
    echo '<input type="checkbox" name="altsize" value="yes"'.($altsize == 'yes' ? ' checked="checked"' : '').' />'.$lang_attachment['text_small_thumbnail'].'&nbsp;';
    echo '<input type="submit" name="submit" value="'.$lang_attachment['submit_upload'].'"'.($count_left ? '' : ' disabled="disabled"').' /> ';
    if ($warning) {
        echo '<span class="striking">'.$warning.'</span>';
    } else {
        echo '<b>'.$lang_attachment['text_left'].'</b><font color="red">'.$count_left.'</font>'.$lang_attachment['text_of'].$count_limit.'&nbsp;&nbsp;&nbsp;<b>'.$lang_attachment['text_size_limit'].'</b>'.Format::size($size_limit).'&nbsp;&nbsp;&nbsp;<b>'.$lang_attachment['text_file_extensions'].'</b>';
        $allowedextsblock = '';
        foreach ($allowed_exts as $ext) {
            $allowedextsblock .= $ext.'/';
        }
        $allowedextsblock = rtrim(trim($allowedextsblock), '/');
        if (! $allowedextsblock) {
            $allowedextsblock = 'N/A';
        }
        echo '<span title="'.htmlspecialchars($allowedextsblock).'"><i>'.$lang_attachment['text_mouse_over_here'].'</i></span>';
    }

    echo '</td>';
    echo '</tr>';
    echo '</form>';
}
?>
</table>
</body>
</html>
