<?php

use App\Support\SupportContext;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($lang_clearcache)) {
    $lang_clearcache = (array) (SupportContext::getGlobal('lang_clearcache') ?? []);
}

$done = (bool) ($done ?? false);
$error = (string) ($error ?? '');
?>

<h1>Clear cache</h1>
<?php if ($done) { ?>
    <p align="center"><font class="striking">Cache cleared</font></p>
<?php } ?>
<?php if ($error !== '') { ?>
    <p align="center"><font class="striking"><?php echo htmlspecialchars($error); ?></font></p>
<?php } ?>

<form method="post" action="clearcache.php">
<?php echo csrf_field(); ?>
<table border="1" cellspacing="0" cellpadding="5">
    <tr><td class="rowhead">Cache name</td><td><input type="text" name="cachename" size="40"></td></tr>
    <tr><td class="rowhead">Multi languages</td><td><input type="checkbox" name="multilang" value="yes">Yes</td></tr>
    <tr><td colspan="2" align="center"><input type="submit" value="Okay" class="btn"></td></tr>
</table>
</form>
