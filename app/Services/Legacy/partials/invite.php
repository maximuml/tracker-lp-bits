<?php

use App\Models\Invite;
use App\Models\Setting;
use App\Repositories\InviteRepository;
use App\Repositories\UserRepository;
use App\Support\Config\SiteConfig;
use App\Support\Format;
use App\Support\Locale;
use App\Support\Pagination;
use App\Support\Ratio;
use App\Support\SupportContext;
use App\Support\UserDisplay;
use Nexus\Nexus;

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (! isset($CURUSER)) {
    $CURUSER = (array) (SupportContext::getUser() ?? []);
}
if (! isset($lang_functions)) {
    $lang_functions = (array) (SupportContext::getGlobal('lang_functions') ?? []);
}
if (! isset($lang_invite)) {
    $lang_invite = (array) (SupportContext::getGlobal('lang_invite') ?? []);
}
$__server_REQUEST_URI = $__server_REQUEST_URI ?? SupportContext::getServerValue('REQUEST_URI');
$id = $id ?? (int) ($CURUSER['id'] ?? 0);
$type = $type ?? '';
$menuSelected = $menuSelected ?? 'invitee';
$pageSize = 50;
$userRep = $userRep ?? new UserRepository;

if (! function_exists('inviteMenu')) {
    function inviteMenu(string $selected = 'invitee'): void
    {
        $lang_invite = (array) (SupportContext::getGlobal('lang_invite') ?? []);
        $id = SupportContext::getGlobal('id');
        $CURUSER = SupportContext::getUser() ?? [];
        $invitesystem = SupportContext::getGlobal('invitesystem');
        $userRep = SupportContext::getGlobal('userRep');
        echo "<div id=\"invitenav\" style='position: relative'><ul id=\"invitemenu\" class=\"menu\">";
        echo '<li'.($selected == 'invitee' ? ' class=selected' : '').'><a href="?id='.$id.'&menu=invitee">'.$lang_invite['text_invite_status'].'</a></li>';
        echo '<li'.($selected == 'sent' ? ' class=selected' : '').'><a href="?id='.$id.'&menu=sent">'.$lang_invite['text_sent_invites_status'].'</a></li>';
        echo '<li'.($selected == 'tmp' ? ' class=selected' : '').'><a href="?id='.$id.'&menu=tmp">'.$lang_invite['text_tmp_status'].'</a></li>';
        try {
            $sendBtnText = $userRep->getInviteBtnText($CURUSER['id']);
            $disabled = '';
        } catch (Exception $exception) {
            $sendBtnText = $exception->getMessage();
            $disabled = ' disabled';
        }
        if ($CURUSER['id'] == $id) {
            echo "</ul><form style='position: absolute;top:0;right:0' method=post action=invite.php?id=".htmlspecialchars($id).'&type=new><input type=submit '.$disabled." value='".$sendBtnText."'></form></div>";
        }
    }
}

// $user is supplied as an array by InfoController::invite.
$user = $user ?? [];
echo '<table width=100% class=main border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>';

echo '<h1 align=center><a href="invite.php?id='.$id.'">'.$user['username'].$lang_invite['text_invite_system'].'</a></h1>';
$sent = $sent ?? htmlspecialchars((string) (SupportContext::getQuery('sent') ?? ''));
if ($sent == 1) {
    $msg = $lang_invite['text_invite_code_sent'];
    echo '<p align=center><font color=red>'.$msg.'</font></p>';
}

$inv = $inv ?? $user;
$_s = $_s ?? (($inv['invites'] != 1) ? ($lang_invite['text_s'] ?? 's') : '');

if ($type == 'new') {
    $SITENAME = $SITENAME ?? Setting::getSiteName();
    $temporaryInvites = $temporaryInvites ?? collect();
    $inviteSelectOptions = $inviteSelectOptions ?? '';
    $preUsernameTr = $preUsernameTr ?? '';
    $invitation_body = $invitation_body ?? sprintf($lang_invite['text_invitation_body'], $SITENAME).($CURUSER['username'] ?? '');

    echo '<form method=post action=takeinvite.php?id='.htmlspecialchars($id).'>'.
    '<table border=1 width=100% cellspacing=0 cellpadding=5>'.
    '<tr align=center><td colspan=2><b>'.$lang_invite['text_invite_someone']."$SITENAME ({$inv['invites']}".$lang_invite['text_invitation'].$_s.$lang_invite['text_left'].' + '.sprintf($lang_invite['text_temporary_left'], count($temporaryInvites)).')</b></td></tr>'.
    '<tr><td class="rowhead nowrap" valign="top" align="right">'.$lang_invite['text_email_address'].'</td><td align=left><input type=text size=40 name=email><br /><font align=left class=small>'.$lang_invite['text_email_address_note'].'</font></td></tr>'.$preUsernameTr.
    '<tr><td class="rowhead nowrap" valign="top" align="right">'.$lang_invite['text_consume_invite']."</td><td align=left><select name='hash'>".$inviteSelectOptions.'</select></td></tr>'.
    '<tr><td class="rowhead nowrap" valign="top" align="right">'.$lang_invite['text_message']."</td><td align=left><textarea name=body rows=10 style='width: 100%'>".$invitation_body.'</textarea></td></tr>'.
    "<tr><td align=center colspan=2><input type=submit value='".$lang_invite['submit_invite']."'></td></tr>".
    '</form></table></td></tr></table>';

} else {
    inviteMenu($menuSelected);
    if ($menuSelected == 'invitee') {
        $status = $status ?? '';
        $enabled = $enabled ?? '';
        $filters = [
            'status' => $status,
            'enabled' => $enabled,
        ];
        $number = InviteRepository::countInvitees($id, $filters);
        $textSelectOnePlease = Locale::trans('nexus.select_one_please', [], null);
        $enabledOptions = $statusOptions = '';
        foreach (['yes', 'no'] as $item) {
            $enabledOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $item, ($enabled !== '' && $enabled == $item) ? ' selected' : '', strtoupper($item)
            );
        }
        foreach (['pending' => $lang_invite['text_pending'], 'confirmed' => $lang_invite['text_confirmed']] as $name => $text) {
            $statusOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $name, ($status !== '' && $status == $name) ? ' selected' : '', $text
            );
        }

        $resetText = Locale::trans('label.reset', [], null);
        $submitText = Locale::trans('label.submit', [], null);
        $filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$__server_REQUEST_URI}" method="get">
        <input type="hidden" name="menu" value="{$menuSelected}" />
        <input type="hidden" name="id" value="{$id}" />
        <span>{$lang_invite['text_enabled']}:</span>
        <select name="enabled">
            <option value="">-{$textSelectOnePlease}-</option>
            {$enabledOptions}
        </select>
        &nbsp;&nbsp;
        <span>{$lang_invite['text_status']}:</span>
        <select name="status">
            <option value="">-{$textSelectOnePlease}-</option>
            {$statusOptions}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$submitText}">
        <input type="button" id="reset" value="{$resetText}">
    </form>
</div>
FORM;
        $resetJs = <<<'JS'
jQuery("#reset").on('click', function () {
    jQuery("select[name=status]").val('')
    jQuery("select[name=enabled]").val('')
})
JS;
        Nexus::js($resetJs, 'footer', false);
        echo $filterForm.'<table border=1 width=100% cellspacing=0 cellpadding=5>'.
            '<form method=post action=takeconfirm.php?id='.htmlspecialchars($id).'>';

        if (! $number) {
            echo '<tr><td colspan=7 align=center>'.$lang_invite['text_no_invites'].'</tr>';
        } else {
            [$pagertop, $pagerbottom, $limit, $offset] = Pagination::pager($pageSize, $number, "?id=$id&menu=$menuSelected&");
            $haremAdditionFactor = SiteConfig::current()->bonus->haremAddition();
            $inviteRows = InviteRepository::getInvitees($id, $filters, (int) $offset, $pageSize);

            echo '<tr>
<td class=colhead><b>'.$lang_invite['text_username'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_email'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_enabled'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_uploaded_count'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_uploaded'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_downloaded'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_ratio'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_seed_torrent_count'].'</b></td>
<td class=colhead><b>'.$lang_invite['text_seed_torrent_size']."</b></td>
<td class=colhead title={$lang_invite['text_seed_torrent_bonus_per_hour_help']}><b>".$lang_invite['text_seed_torrent_bonus_per_hour'].'</b></td>
';
            if ($haremAdditionFactor > 0) {
                echo '<td class="colhead">'.$lang_invite['harem_addition'].'</td>';
            }
            echo '<td class=colhead><b>'.$lang_invite['text_seed_torrent_last_announce_at'].'</b></td>';
            echo '<td class=colhead><b>'.$lang_invite['text_status'].'</b></td>';
            if ($CURUSER['id'] == $id || UserDisplay::currentClass() >= UC_SYSOP) {
                echo '<td class=colhead><b>'.$lang_invite['text_confirm'].'</b></td>';
            }

            echo '</tr>';
            foreach ($inviteRows as $arr) {
                if ($arr['downloaded'] > 0) {
                    $ratio = number_format($arr['uploaded'] / $arr['downloaded'], 3);
                    $ratio = '<font color='.Ratio::color($ratio).">$ratio</font>";
                } else {
                    if ($arr['uploaded'] > 0) {
                        $ratio = 'Inf.';
                    } else {
                        $ratio = '---';
                    }
                }
                if ($arr['status'] == 'confirmed') {
                    $status = "<a href=userdetails.php?id={$arr['id']}><font color=#1f7309>".$lang_invite['text_confirmed'].'</font></a>';
                } else {
                    $status = "<a href=checkuser.php?id={$arr['id']}><font color=#ca0226>".$lang_invite['text_pending'].'</font></a>';
                }
                echo '<tr class=rowfollow>
                    <td class=rowfollow>'.UserDisplay::username($arr['id']).'</td>
                    <td class=rowfollow>'.$arr['email'].'</td>
                    <td class=rowfollow>'.$arr['enabled'].'</td>
                    <td class=rowfollow>'.$arr['torrent_count'].'</td>
                    <td class=rowfollow>'.Format::size($arr['uploaded']).'</td>
                    <td class=rowfollow>'.Format::size($arr['downloaded']).'</td>
                    <td class=rowfollow>'.$ratio.'</td>
                    <td class=rowfollow>'.number_format($arr['seeding_torrent_count']).'</td>
                    <td class=rowfollow>'.Format::size($arr['seeding_torrent_size']).'</td>
                    <td class=rowfollow>'.number_format($arr['seed_points_per_hour'], 3).'</td>
                ';

                if ($haremAdditionFactor > 0) {
                    echo '<td class=rowfollow>'.number_format(floatval($arr['seed_points_per_hour']) * $haremAdditionFactor, 3).'</td>';
                }
                echo "<td class=rowfollow>{$arr['last_announce_at']}</td>";
                echo "<td class=rowfollow>{$status}</td>";
                if ($CURUSER['id'] == $id || UserDisplay::currentClass() >= UC_SYSOP) {
                    echo '<td class=rowfollow>';
                    if ($arr['status'] == 'pending') {
                        echo '<input type="checkbox" name="conusr[]" value="'.$arr['id'].'" />';
                    }
                    echo '</td>';
                }

                echo '</tr>';
            }
        }

        $arr = $arr ?? [];
        if ($CURUSER['id'] == $id || UserDisplay::currentClass() >= UC_SYSOP) {
            $pendingcount = number_format(InviteRepository::countPendingInvitees((int) $CURUSER['id']));
            $colSpan = 12;
            if ((isset($haremAdditionFactor)) && $haremAdditionFactor > 0) {
                $colSpan += 1;
            }
            if ($pendingcount) {
                echo '<input type=hidden name=email value='.htmlspecialchars($arr['email'] ?? '').'>';
                echo "<tr><td colspan=$colSpan align=right><input type=submit style='height: 20px' value=".$lang_invite['submit_confirm_users'].'></td></tr>';
            }
            echo '</form>';
        }
        echo '</table>';
        echo '</td></tr></table>'.($pagertop ?? '');
    } elseif (in_array($menuSelected, ['sent', 'tmp'])) {
        $number1 = InviteRepository::countInvites($id, $menuSelected);
        echo '<table border=1 width=100% cellspacing=0 cellpadding=5>';
        $pagertop = '';
        $pagerbottom = '';

        if (! $number1) {
            echo '<tr align=center><td colspan=6>'.$lang_functions['text_none'].'</tr>';
        } else {
            [$pagertop, $pagerbottom, $limit, $offset] = Pagination::pager($pageSize, $number1, "?id=$id&menu=$menuSelected&");

            $inviteRows = InviteRepository::getInvites($id, $menuSelected, (int) $offset, $pageSize);

            echo '<tr><td class=colhead>'.$lang_invite['text_email'].'</td><td class=colhead>'.$lang_invite['text_hash'].'</td><td class=colhead>'.$lang_invite['text_send_date'].'</td>';
            if ($menuSelected == 'sent') {
                echo "<td class='colhead'>".$lang_invite['text_hash_status'].'</td>';
            }
            echo "<td class='colhead'>".$lang_invite['text_invitee_user'].'</td>';
            if ($menuSelected == 'tmp') {
                echo "<td class='colhead'>".$lang_invite['text_expired_at'].'</td>';
                echo "<td class='colhead'>".Locale::trans('label.created_at', [], null).'</td>';
            }
            echo '</tr>';
            foreach ($inviteRows as $arr1) {
                $isHashValid = $arr1['valid'] == Invite::VALID_YES;
                $registerLink = '';
                if ($isHashValid) {
                    $registerLink = sprintf('&nbsp;<a href="signup.php?type=invite&invitenumber=%s" title="%s" target="_blank"><small>[%s]</small></a>', $arr1['hash'], $lang_invite['signup_link_help'], $lang_invite['signup_link']);
                }
                $tr = '<tr>';
                $tr .= "<td class=rowfollow>{$arr1['invitee']}</td>";
                $tr .= sprintf('<td class="rowfollow">%s%s</td>', $arr1['hash'], $registerLink);
                $tr .= "<td class=rowfollow>{$arr1['time_invited']}</td>";
                if ($menuSelected == 'sent') {
                    $tr .= '<td class=rowfollow>'.Invite::$validInfo[$arr1['valid']]['text'].'</td>';
                }
                if (! $isHashValid) {
                    $tr .= "<td class=rowfollow><a href=userdetails.php?id={$arr1['invitee_register_uid']}><font color=#1f7309>".$arr1['invitee_register_username'].'</font></a></td>';
                } else {
                    $tr .= "<td class='rowfollow'></td>";
                }
                if ($menuSelected == 'tmp') {
                    $tr .= "<td class=rowfollow>{$arr1['expired_at']}</td>";
                    $tr .= "<td class=rowfollow>{$arr1['created_at']}</td>";
                }
                $tr .= '</tr>';
                echo $tr;
            }
        }
        echo '</table>';
        echo "</td></tr></table>$pagertop";
    }

}

return;
