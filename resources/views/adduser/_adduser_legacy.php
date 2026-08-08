<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_REQUEST_METHOD = \App\Support\SupportContext::getServerValue('REQUEST_METHOD');
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Error", "Access denied.");
if ($__server_REQUEST_METHOD == "POST")
{
//	if (\App\Support\SupportContext::getPost("username") == "" || \App\Support\SupportContext::getPost("password") == "" || \App\Support\SupportContext::getPost("email") == "")
//	stderr("Error", "Missing form data.");
//	if (\App\Support\SupportContext::getPost("password") != \App\Support\SupportContext::getPost("password2"))
//	stderr("Error", "Passwords mismatch.");
//	$email = htmlspecialchars(trim(\App\Support\SupportContext::getPost("email")));
//	$email = safe_email($email);
//	if (!check_email($email))
//	stderr("Error","Invalid email address!");
//
//	$username = \App\Support\SupportContext::getPost("username");
//
//	if (!validusername($username))
//		stderr("Error","Invalid username.");
//	$username = sqlesc($username);
//	$res = sql_query("SELECT id FROM users WHERE username=$username");
//	$arr = mysql_fetch_row($res);
//	if ($arr)
//		stderr("Error","Username already exists!");
//	$password = \App\Support\SupportContext::getPost("password");
//	$email = sqlesc(\App\Support\SupportContext::getPost("email"));
//	$res = sql_query("SELECT id FROM users WHERE email=$email");
//	$arr = mysql_fetch_row($res);
//	if ($arr)
//		stderr("Error","The e-mail address is already in use.");
//	$secret = mksecret();
//	$passhash = sqlesc(md5($secret . $password . $secret));
//	$secret = sqlesc($secret);
//
//	sql_query("INSERT INTO users (added, last_access, secret, username, passhash, status, stylesheet, class,email) VALUES(NOW(), NOW(), $secret, $username, $passhash, 'confirmed', ".$defcss.",".$defaultclass_class.",$email)") or sqlerr(__FILE__, __LINE__);
//	$res = sql_query("SELECT id FROM users WHERE username=$username");
//	$arr = mysql_fetch_row($res);
//	if (!$arr)
//	stderr("Error", "Unable to create the account. The user name is possibly already taken.");

    try {
        $userRep = new \App\Repositories\UserRepository();
        $newUser = $userRep->store([
            'username' => \App\Support\SupportContext::getPost('username'),
            'email' => \App\Support\SupportContext::getPost('email'),
            'password' => \App\Support\SupportContext::getPost('password'),
            'password_confirmation' => \App\Support\SupportContext::getPost('password2'),
        ]);
    } catch (\Exception $e) {
        stderr("ERROR", $e->getMessage());
    }
	header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=".htmlspecialchars($newUser->id));
	return;
}
stdhead("Add user");

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
<?php stdfoot();
