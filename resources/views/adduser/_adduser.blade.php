<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR)
\App\Support\LegacyResponse::abort("Error", "Access denied.");
if ($__server_REQUEST_METHOD == "POST")
{
    try {
        $userRep = new \App\Repositories\UserRepository();
        $newUser = $userRep->store([
            'username' => \App\Support\SupportContext::getPost('username'),
            'email' => \App\Support\SupportContext::getPost('email'),
            'password' => \App\Support\SupportContext::getPost('password'),
            'password_confirmation' => \App\Support\SupportContext::getPost('password2'),
        ]);
    } catch (\Exception $e) {
        \App\Support\LegacyResponse::abort("ERROR", $e->getMessage());
    }
	header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=".htmlspecialchars($newUser->id));
	return;
}
\App\Support\Html::stdhead("Add user");

?>
<h1>Add user</h1>
<form method=post action=adduser.php>
<table border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead>User name</td><td><input type=text name=username size=40></td></tr>
<tr><td class=rowhead>Password</td><td><input type=password name=password size=40></td></tr>
<tr><td class=rowhead>Re-type password</td><td><input type=password name=password2 size=40></td></tr>
<tr><td class=rowhead>E-mail</td><td><input type=text name=email size=40></td></tr>
<tr><td colspan=2 align=center><input type=submit value="Okay" class=btn></td></tr>
</table>
</form>
<?php \App\Support\Html::stdfoot();
