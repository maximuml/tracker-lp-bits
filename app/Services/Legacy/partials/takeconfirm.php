<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_takeconfirm)) $lang_takeconfirm = (array) (\App\Support\SupportContext::getGlobal('lang_takeconfirm') ?? []);
?>
<?php
if (\App\Support\SupportContext::getPost('id') !== null) {
    $id = intval(\App\Support\SupportContext::getPost('id'));
} elseif (\App\Support\SupportContext::getQuery('id') !== null) {
    $id = intval(\App\Support\SupportContext::getQuery('id'));
} else {
    \App\Support\LegacyResponse::abort('Error', 'Invalid id');
}
\App\Support\LegacyResponse::assertId($id, true);
if (($CURUSER['id'] != $id && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_INVITE)) || !\App\Support\Validators::isId($id))
    \App\Support\LegacyResponse::abort($lang_functions['std_sorry'], $lang_functions['std_permission_denied'], true, false);
$email = \App\Support\Input::unescape(htmlspecialchars(trim(\App\Support\SupportContext::getPost("email"))));
if(!empty(\App\Support\SupportContext::getPost('conusr'))) {
//    sql_query("UPDATE users SET status = 'confirmed', editsecret = '' WHERE id IN (" . implode(", ", \App\Support\SupportContext::getPost('conusr')) . ") AND status='pending'");
    $userList = \App\Models\User::query()->whereIn('id', \App\Support\SupportContext::getPost('conusr'))
        ->where('status', 'pending')
        ->where('invited_by', $id)
        ->get(\App\Models\User::$commonFields)
    ;
    if ($userList->isNotEmpty()) {
        $uidArr = [];
        foreach ($userList as $user) {
            $uidArr[] = $user->id;
            \App\Support\Events::fire(\App\Enums\ModelEventEnum::USER_UPDATED, $user, null);
        }
        \App\Models\User::query()->whereIn('id', $uidArr)->update(['status' => 'confirmed', 'editsecret' => '']);
    } else {
        \App\Support\LegacyResponse::abort($lang_takeconfirm['std_sorry'], $lang_takeconfirm['std_no_buddy_to_confirm'].
            "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'], false);
    }
} else {
    \App\Support\LegacyResponse::abort($lang_takeconfirm['std_sorry'], $lang_takeconfirm['std_no_buddy_to_confirm'].
        "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'], false);
}
$title = $SITENAME.$lang_takeconfirm['mail_title'];
$baseUrl = \App\Support\Url::schemeAndHost(false);
$siteName = \App\Models\Setting::getSiteName();
$mailContentTwo = sprintf($lang_takeconfirm['mail_content_two'], $siteName, $REPORTMAIL, $siteName);
$body = <<<EOD
{$lang_takeconfirm['mail_content_1']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/login.php')">{$lang_takeconfirm['mail_here']}</a></b><br />
{$baseUrl}/login.php
{$mailContentTwo}
EOD;

//this mail is sent when the site is using admin(open/closed)/inviter(closed) confirmation and the admin/inviter confirmed the pending user
\App\Support\Mail::sentLegacy((string) $email, (string) $SITENAME, (string) $SITEEMAIL, (string) $title, (string) $body, (string) "invite confirm", (bool) false, (bool) false, '', (string) 'UTF-8');

header("Location: invite.php?id=".htmlspecialchars($CURUSER['id']));
return;
?>
