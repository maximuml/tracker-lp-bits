<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (get_user_class() < UC_SYSOP)
stderr("Error", "Access denied.");
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
if ($_POST["username"] == "" || $_POST["donated"] == "")
stderr("Error", "Missing form data.");
$username = trim($_POST["username"]);
$donated = trim($_POST["donated"]);

$user = \App\Models\User::query()->where('username', $username)->first(['id']);
if (!$user)
	stderr("Error", "Unable to update account.");
\App\Models\User::query()->where('id', $user->id)->update(['donated' => $donated]);
header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=$user->id");
return;
}
stdhead("Update Users Donated Amounts");
?>
<h1>Update Users Donated Amounts</h1>
<form method=post action=donated.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>User name</td><td><input type=text name=username size=40></td></tr>
<tr><td class=rowhead>Donated</td><td><input type=text name=donated size=5></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Okay" class=btn></td></tr>
</table>
</form>
<?php stdfoot();
