<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($lang_makepoll)) $lang_makepoll = (array) (\App\Support\SupportContext::getGlobal('lang_makepoll') ?? []);

$poll = (array) ($poll ?? []);
$pollid = (int) ($poll['id'] ?? 0);
$ageWarning = (string) ($ageWarning ?? '');
$returnto = (string) ($returnto ?? '');

if ($pollid > 0) {
    print("<h1>" . ($lang_makepoll['text_edit_poll'] ?? 'Edit poll') . "</h1>");
} else {
    if ($ageWarning !== '') {
        print("<p><font class=striking><b>" . $ageWarning . "</b></font></p>");
    }
    print("<h1>" . ($lang_makepoll['text_make_poll'] ?? 'Make poll') . "</h1>");
}
?>

<form method="post" action="makepoll.php">
<?php echo csrf_field(); ?>
<style type="text/css">
input.mp { width: 450px; }
</style>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead><?php echo $lang_makepoll['text_question'] ?? 'Question'; ?> <font color=red>*</font></td><td align=left><input name=question class=mp maxlength=255 value="<?php echo htmlspecialchars((string) ($poll['question'] ?? '')); ?>"></td></tr>
<?php for ($i = 0; $i <= 19; $i++) { ?>
<tr><td class=rowhead><?php echo ($lang_makepoll['text_option'] ?? 'Option') . ($i + 1); ?><?php echo $i < 2 ? ' <font color=red>*</font>' : ''; ?></td><td align=left><input name=option<?php echo $i; ?> class=mp maxlength=40 value="<?php echo htmlspecialchars((string) ($poll["option{$i}"] ?? '')); ?>"><br /></td></tr>
<?php } ?>
<tr><td colspan=2 align=center><input type=submit value="<?php echo $pollid ? ($lang_makepoll['submit_edit_poll'] ?? 'Edit poll') : ($lang_makepoll['submit_create_poll'] ?? 'Create poll'); ?>" style='height: 20pt'></td></tr>
</table>
<p><font color=red>*</font><?php echo $lang_makepoll['text_required'] ?? 'Required'; ?></p>
<?php if ($pollid > 0): ?>
<input type=hidden name=pollid value="<?php echo $pollid; ?>">
<?php endif; ?>
<input type=hidden name=returnto value="<?php echo htmlspecialchars($returnto); ?>">
</form>
