<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_attachment)) $lang_attachment = (array) (\App\Support\SupportContext::getGlobal('lang_attachment') ?? []);

// Variables are precomputed by UtilityController::attachment.
$Attach = $Attach ?? new \App\Support\Attachment\AttachmentService($CURUSER['id'] ?? 0);
$count_limit = $count_limit ?? (int) $Attach->get_count_limit();
$count_left = $count_left ?? $Attach->get_count_left();
$size_limit = $size_limit ?? $Attach->get_size_limit_byte();
$allowed_exts = $allowed_exts ?? $Attach->get_allowed_ext();
$css_uri = $css_uri ?? \App\Support\Style::cssUriWithContext();
$altsize = $altsize ?? '';
$callback_func = $callback_func ?? '';
$warning = $warning ?? '';
$script = $script ?? '';
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="<?php echo \App\Support\Style::fontCssUriWithContext()?>" type="text/css">
<link rel="stylesheet" href="<?php echo $css_uri."theme.css"?>" type="text/css">
</head>
<body class="inframe">
<table width="100%">
<?php
echo $script;
if ($Attach->enable_attachment())
{
    print("<form enctype=\"multipart/form-data\" name=\"attachment\" method=\"post\" action=\"attachment.php?callback_func=" . htmlspecialchars($callback_func) . "\">");
    print("<tr>");
    print("<td class=\"embedded\" colspan=\"2\" align=left>");
    print("<input type=\"file\" name=\"file\"".($count_left ? "" : " disabled=\"disabled\"")." />&nbsp;");
    print("<input type=\"checkbox\" name=\"altsize\" value=\"yes\"".($altsize == 'yes' ? " checked=\"checked\"" : "")." />".$lang_attachment['text_small_thumbnail']."&nbsp;");
    print("<input type=\"submit\" name=\"submit\" value=\"".$lang_attachment['submit_upload']."\"".($count_left ? "" : " disabled=\"disabled\"")." /> ");
    if ($warning) {
        print('<span class="striking">'.$warning.'</span>');
    } else {
        print("<b>".$lang_attachment['text_left']."</b><font color=\"red\">".$count_left."</font>".$lang_attachment['text_of'].$count_limit."&nbsp;&nbsp;&nbsp;<b>".$lang_attachment['text_size_limit']."</b>".\App\Support\Format::size($size_limit)."&nbsp;&nbsp;&nbsp;<b>".$lang_attachment['text_file_extensions']."</b>");
        $allowedextsblock = "";
        foreach($allowed_exts as $ext) {
            $allowedextsblock .= $ext."/";
        }
        $allowedextsblock = rtrim(trim($allowedextsblock), "/");
        if (!$allowedextsblock) {
            $allowedextsblock = 'N/A';
        }
        print("<span title=\"".htmlspecialchars($allowedextsblock)."\"><i>".$lang_attachment['text_mouse_over_here']."</i></span>");
    }

    print("</td>");
    print("</tr>");
    print("</form>");
}
?>
</table>
</body>
</html>
