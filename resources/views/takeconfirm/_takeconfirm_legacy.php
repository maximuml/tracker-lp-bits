<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$id =  ((\App\Support\SupportContext::getPost('id') !== null)) ? intval(\App\Support\SupportContext::getPost('id')) : (((\App\Support\SupportContext::getQuery('id') !== null)) ? intval(\App\Support\SupportContext::getQuery('id')) : die());
int_check($id,true);
if (($CURUSER['id'] != $id && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_INVITE)) || !is_valid_id($id))
    stderr($lang_functions['std_sorry'],$lang_functions['std_permission_denied'], true, false);
$email = unesc(htmlspecialchars(trim(\App\Support\SupportContext::getPost("email"))));
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
            fire_event(\App\Enums\ModelEventEnum::USER_UPDATED, $user);
        }
        \App\Models\User::query()->whereIn('id', $uidArr)->update(['status' => 'confirmed', 'editsecret' => '']);
    } else {
        stderr($lang_takeconfirm['std_sorry'],$lang_takeconfirm['std_no_buddy_to_confirm'].
            "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'],false);
    }
} else {
    stderr($lang_takeconfirm['std_sorry'],$lang_takeconfirm['std_no_buddy_to_confirm'].
        "<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_takeconfirm['std_here_to_go_back'],false);
}
$title = $SITENAME.$lang_takeconfirm['mail_title'];
$baseUrl = getSchemeAndHttpHost();
$siteName = \App\Models\Setting::getSiteName();
$mailContentTwo = sprintf($lang_takeconfirm['mail_content_two'], $siteName, $REPORTMAIL, $siteName);
$body = <<<EOD
{$lang_takeconfirm['mail_content_1']}
<b><a href="javascript:void(null)" onclick="window.open('{$baseUrl}/login.php')">{$lang_takeconfirm['mail_here']}</a></b><br />
{$baseUrl}/login.php
{$mailContentTwo}
EOD;

//this mail is sent when the site is using admin(open/closed)/inviter(closed) confirmation and the admin/inviter confirmed the pending user
sent_mail($email,$SITENAME,$SITEEMAIL,$title,$body,"invite confirm",false,false,'');

header("Location: invite.php?id=".htmlspecialchars($CURUSER['id']));
return;
