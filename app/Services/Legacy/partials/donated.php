<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($lang_donated)) $lang_donated = (array) (\App\Support\SupportContext::getGlobal('lang_donated') ?? []);

$error = (string) ($error ?? '');
?>

<h1>Update Users Donated Amounts</h1>
<?php if ($error !== ''): ?>
    <p align="center"><font class="striking"><?php echo htmlspecialchars($error); ?></font></p>
<?php endif; ?>
<form method="post" action="donated.php">
<?php echo csrf_field(); ?>
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">User name</td><td><input type="text" name="username" size="40"></td></tr>
    <tr><td class="rowhead">Donated</td><td><input type="text" name="donated" size="5"></td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
