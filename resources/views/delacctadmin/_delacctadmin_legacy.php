<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::USER_DELETE);

if ($__server_REQUEST_METHOD == "POST")
{
$userid = trim(\App\Support\SupportContext::getPost("userid"));

if (!$userid)
  \App\Support\LegacyResponse::abort("Error", "Please fill out the form correctly.");

$user = \App\Models\User::query()->where('id', $userid)->first();
if (!$user)
  \App\Support\LegacyResponse::abort("Error", "Bad user id or password. Please verify that all entered information is correct.");
$arr = $user->toArray();

$id = $arr['id'];
$name = $arr['username'];
$userRep = new \App\Repositories\UserRepository();
$userRep->destroy($id);
\App\Support\LegacyResponse::abort("Success", "The account <b>".htmlspecialchars($name)."</b> was deleted.", false);
}
\App\Support\Html::stdhead("Delete account");
?>
<h1>Delete account</h1>
<table border=1 cellspacing=0 cellpadding=5>
<form method=post action=delacctadmin.php>
<tr><td class=rowhead>User name</td><td><input size=40 name=userid></td></tr>

<tr><td colspan=2><input type=submit class=btn value='Delete'></td></tr>
</form>
</table>
<?php
\App\Support\Html::stdfoot();
